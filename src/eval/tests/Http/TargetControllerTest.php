<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Eval\Http\TargetController;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpFoundation\Request;

final class TargetControllerTest extends TestCase
{
    public function testAnswersAPrompt()
    {
        $result = new TextResult('Hello!');
        $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 10, completionTokens: 5, totalTokens: 15));
        $controller = new TargetController(new Agent(MockPlatformFactory::createPlatform(static fn () => $result), 'model'));

        $response = $controller($this->request(['prompt' => 'Hi']));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('Hello!', $data['output']);
        $this->assertIsString($data['run_id']);
        $this->assertNotSame('', $data['run_id']);
        $this->assertSame(['prompt' => 10, 'completion' => 5, 'total' => 15], $data['token_usage']);
        $this->assertSame([], $data['tool_calls']);
    }

    public function testPassesTheMessagesToTheAgent()
    {
        $agent = new class implements AgentInterface {
            public ?MessageBag $messages = null;

            public function call(string|MessageBag|UserMessage $input, array $options = []): ExecutionInterface
            {
                $this->messages = $input instanceof MessageBag ? $input : null;

                return (new Agent(MockPlatformFactory::createPlatform('Sure.'), 'model'))->call($input, $options);
            }

            public function getName(): string
            {
                return 'agent';
            }
        };

        $response = (new TargetController($agent))($this->request(['messages' => [
            ['role' => 'system', 'content' => 'You are a support agent.'],
            ['role' => 'user', 'content' => 'Refund SO-1'],
            ['role' => 'assistant', 'content' => 'Which item?'],
            ['role' => 'user', 'content' => 'The jacket'],
        ]]));

        $this->assertSame('Sure.', json_decode((string) $response->getContent(), true)['output']);
        $this->assertNotNull($agent->messages);
        $this->assertSame(
            [Role::System, Role::User, Role::Assistant, Role::User],
            array_map(static fn ($message): Role => $message->getRole(), $agent->messages->getMessages()),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRequests(): iterable
    {
        yield 'invalid json' => ['{', 'Could not decode request body.'];
        yield 'no prompt' => ['{"input": "Hi"}', 'The request needs a "prompt" string or a "messages" list.'];
        yield 'empty prompt' => ['{"prompt": ""}', 'The request needs a "prompt" string or a "messages" list.'];
        yield 'message without content' => ['{"messages": [{"role": "user"}]}', 'The message 0 needs a "content" string.'];
        yield 'unknown role' => ['{"messages": [{"role": "tool", "content": "42"}]}', 'The message 0 needs a "role" of "system", "user" or "assistant".'];
    }

    #[DataProvider('invalidRequests')]
    public function testRejectsInvalidRequests(string $body, string $error)
    {
        $controller = new TargetController(new Agent(MockPlatformFactory::createPlatform('Hello!'), 'model'));

        $response = $controller(Request::create('/', 'POST', content: $body));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['error' => $error], json_decode((string) $response->getContent(), true));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): Request
    {
        return Request::create('/', 'POST', content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
