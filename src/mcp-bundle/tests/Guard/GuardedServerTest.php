<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Guard;

use Mcp\Capability\Registry;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Mcp\Server;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\Guard\GuardedRegistry;
use Symfony\AI\McpBundle\Guard\McpGuardInterface;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

final class GuardedServerTest extends TestCase
{
    private McpController $controller;
    private string $sessionId;

    protected function setUp(): void
    {
        $guard = new class implements McpGuardInterface {
            public function isGranted(Tool|Prompt|ResourceDefinition|ResourceTemplate $element): bool
            {
                return !str_starts_with($element instanceof ResourceTemplate ? $element->uriTemplate : $element->name, 'secret');
            }
        };

        $server = Server::builder()
            ->setRegistry(new GuardedRegistry(new Registry(), [$guard]))
            ->setPaginationLimit(2)
            ->addTool(static fn (): string => 'a', 'alpha')
            ->addTool(static fn (): string => 's', 'secret_tool')
            ->addTool(static fn (): string => 'b', 'beta')
            ->addTool(static fn (): string => 'c', 'gamma')
            ->addPrompt(static fn (): array => [['role' => 'user', 'content' => 'hi']], 'greet')
            ->addPrompt(static fn (): array => [['role' => 'user', 'content' => 'psst']], 'secret_prompt')
            ->addResourceTemplate(static fn (string $id): string => $id, 'secret://{id}', 'hidden')
            ->addResourceTemplate(static fn (string $id): string => $id, 'public://{id}', 'shown')
            ->build();

        $psr17Factory = new Psr17Factory();
        $this->controller = new McpController(
            $server,
            new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory),
            new HttpFoundationFactory(),
            $psr17Factory,
            $psr17Factory,
            new MiddlewareFactory(null),
        );

        $response = $this->controller->handle($this->createRequest([
            'jsonrpc' => '2.0',
            'id' => 0,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1.0']],
        ]));
        $this->sessionId = (string) $response->headers->get('Mcp-Session-Id');
        $this->controller->handle($this->createRequest(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
    }

    public function testListsLeaveOutDeniedElementsAndKeepPagesFull()
    {
        $firstPage = $this->call('tools/list');
        $this->assertSame(['alpha', 'beta'], array_column($firstPage['result']['tools'], 'name'));
        $this->assertArrayHasKey('nextCursor', $firstPage['result']);

        $secondPage = $this->call('tools/list', ['cursor' => $firstPage['result']['nextCursor']]);
        $this->assertSame(['gamma'], array_column($secondPage['result']['tools'], 'name'));
        $this->assertArrayNotHasKey('nextCursor', $secondPage['result']);

        $this->assertSame(['greet'], array_column($this->call('prompts/list')['result']['prompts'], 'name'));
        $this->assertSame(['public://{id}'], array_column($this->call('resources/templates/list')['result']['resourceTemplates'], 'uriTemplate'));
    }

    public function testDeniedElementsAreAnsweredAsNotFound()
    {
        $this->assertSame('a', $this->call('tools/call', ['name' => 'alpha', 'arguments' => new \stdClass()])['result']['content'][0]['text']);

        $toolResponse = $this->call('tools/call', ['name' => 'secret_tool', 'arguments' => new \stdClass()]);
        $missingToolResponse = $this->call('tools/call', ['name' => 'missing_tool', 'arguments' => new \stdClass()]);
        $this->assertSame($missingToolResponse['error']['code'], $toolResponse['error']['code']);
        $this->assertStringContainsString('secret_tool', $toolResponse['error']['message']);

        $this->assertArrayHasKey('error', $this->call('prompts/get', ['name' => 'secret_prompt']));
        $this->assertArrayHasKey('result', $this->call('prompts/get', ['name' => 'greet']));

        $this->assertArrayHasKey('error', $this->call('resources/read', ['uri' => 'secret://42']));
        $this->assertArrayHasKey('result', $this->call('resources/read', ['uri' => 'public://42']));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function call(string $method, array $params = []): array
    {
        $response = $this->controller->handle($this->createRequest(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params ?: new \stdClass()]));

        $content = (string) $response->getContent();
        if (str_starts_with($content, 'event:') || str_starts_with($content, 'data:')) {
            preg_match('/^data: (.*)$/m', $content, $matches);
            $content = $matches[1];
        }

        return json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function createRequest(array $message): Request
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
        if (isset($this->sessionId)) {
            $headers['HTTP_MCP_SESSION_ID'] = $this->sessionId;
            $headers['HTTP_MCP_PROTOCOL_VERSION'] = '2025-06-18';
        }

        return Request::create('http://localhost/mcp', 'POST', server: $headers, content: json_encode($message, \JSON_THROW_ON_ERROR));
    }
}
