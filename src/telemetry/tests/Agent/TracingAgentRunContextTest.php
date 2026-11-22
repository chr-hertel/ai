<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Tests\Agent;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Budget\RunBudget;
use Symfony\AI\Agent\Event\GuardrailTriggered;
use Symfony\AI\Agent\Prompt\InMemoryPromptRegistry;
use Symfony\AI\Agent\Prompt\PromptInputProcessor;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Telemetry\Agent\TracingAgent;
use Symfony\AI\Telemetry\EventListener\GuardrailSpanListener;
use Symfony\AI\Telemetry\RegexContentRedactor;
use Symfony\AI\Telemetry\Tests\InMemoryTracingTrait;
use Symfony\AI\Telemetry\UserIdResolverInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class TracingAgentRunContextTest extends TestCase
{
    use InMemoryTracingTrait;

    public function testRunCarriesTheTraceIdNextToItsOwnRunId()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support'), $this->tracer);

        $execution = $agent->call('Hello');
        $metadata = $execution->getMetadata();

        $span = $this->span('invoke_agent support');
        $runContext = $metadata->get('run_context');
        $this->assertSame($execution->getId()->toRfc4122(), $metadata->get('run_id'));
        $this->assertInstanceOf(RunContext::class, $runContext);
        $this->assertSame($span->getTraceId(), $metadata->get('trace_id'));
        $this->assertSame($span->getTraceId(), $runContext->getTraceId());
        $this->assertNotSame($span->getTraceId(), $metadata->get('run_id'));
        $this->assertSame($metadata->get('run_id'), $span->getAttributes()->get('app.run_id'));
    }

    public function testRunsWithinOneTraceHaveTheirOwnRunIds()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support'), $this->tracer);

        $request = $this->tracer->spanBuilder('GET /support')->startSpan();
        $scope = $request->activate();
        try {
            $first = $agent->call('Hello')->getMetadata();
            $second = $agent->call('Hello again')->getMetadata();
        } finally {
            $scope->detach();
            $request->end();
        }

        $this->assertSame($first->get('trace_id'), $second->get('trace_id'));
        $this->assertNotSame($first->get('run_id'), $second->get('run_id'));
    }

    public function testRunContextAttributesDoNotOverwriteTheRunFields()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support'), $this->tracer);

        $agent->call('Hello', ['run_context' => new RunContext('run-1', release: 'v1', attributes: ['run_id' => 'fake', 'release' => 'fake'])])->getContent();

        $attributes = $this->span('invoke_agent support')->getAttributes();
        $this->assertSame('run-1', $attributes->get('app.run_id'));
        $this->assertSame('v1', $attributes->get('app.release'));
    }

    public function testSpanOfAnExecutionThatIsNeverConsumedIsEnded()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support'), $this->tracer);

        $execution = $agent->call('Hello');
        $this->assertCount(0, $this->spans());

        unset($execution);

        $this->assertCount(1, $this->spansNamed('invoke_agent support'));
    }

    public function testRunContextOfTheCallerIsRecordedOnTheSpan()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', [new PromptInputProcessor(new InMemoryPromptRegistry(['support/system' => ['v3' => 'Be nice.']]), 'support/system')], name: 'support'), $this->tracer);

        $metadata = $agent->call('Hello', ['run_context' => new RunContext('', 'customer-42', 'v1.2.3', attributes: ['channel' => 'web'])])->getMetadata();

        $attributes = $this->span('invoke_agent support')->getAttributes();
        $this->assertSame($metadata->get('run_id'), $attributes->get('app.run_id'));
        $this->assertSame('v1.2.3', $attributes->get('app.release'));
        $this->assertSame('web', $attributes->get('app.channel'));
        $this->assertNull($attributes->get('user.id'), 'Users are only recorded with a user ID resolver');
        // only known once the prompt was resolved during the run
        $this->assertSame('support/system', $attributes->get('app.prompt.name'));
        $this->assertSame('v3', $attributes->get('app.prompt.version'));
    }

    public function testExplicitRunIdIsKept()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support'), $this->tracer);

        $metadata = $agent->call('Hello', ['run_context' => new RunContext('run-1')])->getMetadata();

        $this->assertSame('run-1', $metadata->get('run_id'));
        $this->assertSame('run-1', $this->span('invoke_agent support')->getAttributes()->get('app.run_id'));
    }

    public function testUserOfTheRunContextIsRecordedIfTheResolverKnowsNoUser()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support'), $this->tracer, userIdResolver: new FixedUserIdResolver(null));

        $agent->call('Hello', ['run_context' => new RunContext('run-1', 'customer-42')])->getContent();

        $this->assertSame('customer-42', $this->span('invoke_agent support')->getAttributes()->get('user.id'));
    }

    public function testUserOfTheResolverIsNotOverwrittenByTheRunContext()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support'), $this->tracer, userIdResolver: new FixedUserIdResolver('pseudonym-7'));

        $agent->call('Hello', ['run_context' => new RunContext('run-1', 'jane@example.com')])->getContent();

        $this->assertSame('pseudonym-7', $this->span('invoke_agent support')->getAttributes()->get('user.id'));
    }

    public function testExceptionMessagesAreRedacted()
    {
        $platform = MockPlatformFactory::createPlatform(static fn (): never => throw new \RuntimeException('No customer jane@example.com'));
        $agent = new TracingAgent(new Agent($platform, 'gpt-4.1', name: 'support'), $this->tracer, redactor: new RegexContentRedactor());

        try {
            $agent->call('Hello')->getContent();
            $this->fail('The exception of the platform is rethrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('No customer jane@example.com', $e->getMessage());
        }

        $span = $this->span('invoke_agent support');
        $this->assertSame('No customer [email]', $span->getStatus()->getDescription());
        $this->assertSame('No customer [email]', $span->getEvents()[0]->getAttributes()->get('exception.message'));
    }

    public function testCapturedContentIsRedacted()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Mail sent to jane@example.com.'), 'gpt-4.1', name: 'support'), $this->tracer, true, redactor: new RegexContentRedactor());

        $agent->call('My card is 4111 1111 1111 1111, mail me at jane@example.com')->getContent();

        $attributes = $this->span('invoke_agent support')->getAttributes();
        $this->assertStringNotContainsString('jane@example.com', (string) $attributes->get('gen_ai.input.messages'));
        $this->assertStringNotContainsString('4111', (string) $attributes->get('gen_ai.input.messages'));
        $this->assertStringContainsString('[card]', (string) $attributes->get('gen_ai.input.messages'));
        $this->assertStringContainsString('[email]', (string) $attributes->get('gen_ai.output.messages'));
    }

    public function testGuardrailInterventionIsAnEventOnTheAgentSpan()
    {
        $dispatcher = new EventDispatcher();
        $budget = new RunBudget(maxTokens: 10, eventDispatcher: $dispatcher);
        $dispatcher->addListener(ToolCallsExecuted::class, $budget);
        $dispatcher->addListener(GuardrailTriggered::class, new GuardrailSpanListener());

        $platform = MockPlatformFactory::createPlatform(static function (): ResultInterface {
            $result = new ToolCallResult([new ToolCall('call-1', 'ticker')]);
            $result->getMetadata()->add('token_usage', new TokenUsage(totalTokens: 100));

            return $result;
        });

        $agent = new TracingAgent(new Agent($platform, 'gpt-4.1', name: 'support', toolbox: new Toolbox([new Ticker()]), eventDispatcher: $dispatcher), $this->tracer);
        $agent->call('What time is it?')->getContent();

        $events = $this->span('invoke_agent support')->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('guardrail.triggered', $events[0]->getName());
        $this->assertSame('run_budget', $events[0]->getAttributes()->get('guardrail.name'));
        $this->assertSame('stopped', $events[0]->getAttributes()->get('guardrail.action'));
    }
}

final class FixedUserIdResolver implements UserIdResolverInterface
{
    public function __construct(
        private readonly ?string $userId,
    ) {
    }

    public function resolve(): ?string
    {
        return $this->userId;
    }
}

#[AsTool('ticker', 'Returns a tick')]
final class Ticker
{
    public function __invoke(): string
    {
        return 'tick';
    }
}
