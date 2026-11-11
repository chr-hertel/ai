<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Security;

use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Registry;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Security\IsGrantedGuard;
use Symfony\AI\McpBundle\Security\IsGrantedListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class IsGrantedListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideDeniedMessages(): iterable
    {
        yield 'tool call' => [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'publish']]];
        yield 'resource read through a template' => [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'resources/read', 'params' => ['uri' => 'draft://42']]];
        yield 'batch' => [[
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'publish']],
        ]];
    }

    #[DataProvider('provideDeniedMessages')]
    public function testDeniesACalledElement(mixed $body)
    {
        $this->expectException(AccessDeniedException::class);

        ($this->listener())($this->event($body));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideIgnoredRequests(): iterable
    {
        yield 'list' => [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], 'mcp.server.default.controller::handle'];
        yield 'unknown tool' => [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'missing']], 'mcp.server.default.controller::handle'];
        yield 'invalid body' => ['{', 'mcp.server.default.controller::handle'];
        yield 'another controller' => [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'publish']], 'mcp.server.other.controller::handle'];
    }

    #[DataProvider('provideIgnoredRequests')]
    public function testIgnores(mixed $body, string $controller)
    {
        $event = $this->event($body, $controller);

        ($this->listener())($event);

        $this->assertSame([], $event->getAttributes());
    }

    private function listener(): IsGrantedListener
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('publish', null, ['type' => 'object'], null, null), [Drafts::class, 'publish']);
        $registry->registerResourceTemplate(new ResourceTemplate('draft://{id}', 'draft'), [Drafts::class, 'read']);

        $checker = new class implements AuthorizationCheckerInterface {
            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return false;
            }
        };

        $guard = new IsGrantedGuard($checker, [
            'tool' => ['publish' => [Drafts::class, 'publish']],
            'resource_template' => ['draft://{id}' => [Drafts::class, 'read']],
        ]);

        return new IsGrantedListener('mcp.server.default.controller::handle', $registry, $guard);
    }

    private function event(mixed $body, string $controller = 'mcp.server.default.controller::handle'): ControllerEvent
    {
        $request = Request::create('/mcp', 'POST', content: \is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR));
        $request->attributes->set('_controller', $controller);

        return new ControllerEvent($this->createStub(HttpKernelInterface::class), static fn () => null, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}

final class Drafts
{
    #[McpTool(name: 'publish')]
    #[IsGranted('ROLE_EDITOR')]
    public function publish(): string
    {
        return 'published';
    }

    #[McpResourceTemplate(uriTemplate: 'draft://{id}', name: 'draft')]
    #[IsGranted('ROLE_EDITOR')]
    public function read(string $id): string
    {
        return $id;
    }
}
