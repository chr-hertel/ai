<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\Tests\Agent;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Budget\RunBudget;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Context\PromptReference;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Event\GuardrailTriggered;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\OpenTelemetryBridge\Agent\TracingAgent;
use Symfony\AI\OpenTelemetryBridge\EventListener\GuardrailSpanListener;
use Symfony\AI\OpenTelemetryBridge\RegexContentRedactor;
use Symfony\AI\OpenTelemetryBridge\Tests\InMemoryTracingTrait;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class TracingAgentRunContextTest extends TestCase
{
    use InMemoryTracingTrait;

    public function testRunIdIsTheTraceId()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support'), $this->tracer);

        $metadata = $agent->call('Hello')->getMetadata();

        $span = $this->span('invoke_agent support');
        $this->assertSame($span->getTraceId(), $metadata->get('trace_id'));
        $this->assertSame($span->getTraceId(), $metadata->get('run_id'));
        $this->assertSame($span->getTraceId(), $span->getAttributes()->get('app.run_id'));
    }

    public function testRunContextOfTheCallerIsRecordedOnTheSpan()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support', instruction: new Instruction('Be nice.', new PromptReference('support/system', 'v3'))), $this->tracer);

        $metadata = $agent->call('Hello', new Context(new RunContext('', 'customer-42', 'v1.2.3', attributes: ['channel' => 'web'])))->getMetadata();

        $attributes = $this->span('invoke_agent support')->getAttributes();
        $this->assertSame($metadata->get('trace_id'), $metadata->get('run_id'));
        $this->assertSame('v1.2.3', $attributes->get('app.release'));
        $this->assertSame('web', $attributes->get('app.channel'));
        $this->assertSame('customer-42', $attributes->get('user.id'));
        // only known once the instruction was rendered during the run
        $this->assertSame('support/system', $attributes->get('app.prompt.name'));
        $this->assertSame('v3', $attributes->get('app.prompt.version'));
    }

    public function testExplicitRunIdIsKept()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support'), $this->tracer);

        $metadata = $agent->call('Hello', new Context(new RunContext('run-1')))->getMetadata();

        $this->assertSame('run-1', $metadata->get('run_id'));
        $this->assertSame('run-1', $this->span('invoke_agent support')->getAttributes()->get('app.run_id'));
    }

    public function testCapturedContentIsRedacted()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Mail sent to jane@example.com.'), 'gpt-4.1', name: 'support'), $this->tracer, true, redactor: new RegexContentRedactor());

        $agent->call('My card is 4111 1111 1111 1111, mail me at jane@example.com')->getContent();

        $attributes = $this->span('invoke_agent support')->getAttributes();
        $this->assertStringNotContainsString('jane@example.com', (string) $attributes->get('gen_ai.input.messages'));
        $this->assertStringNotContainsString('4111', (string) $attributes->get('gen_ai.input.messages'));
        $this->assertStringContainsString('[email]', (string) $attributes->get('output.value'));
        $this->assertStringContainsString('[card]', (string) $attributes->get('input.value'));
    }

    public function testGuardrailInterventionIsAnEventOnTheAgentSpan()
    {
        $dispatcher = new EventDispatcher();
        $budget = new RunBudget(maxTokens: 10, eventDispatcher: $dispatcher);
        foreach (RunBudget::getSubscribedEvents() as $event => $method) {
            $dispatcher->addListener($event, [$budget, $method]);
        }
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

#[AsTool('ticker', 'Returns a tick')]
final class Ticker
{
    public function __invoke(): string
    {
        return 'tick';
    }
}
