<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Budget;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Budget\RunBudget;
use Symfony\AI\Agent\Event\GuardrailTriggered;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Cost\CostCalculator;
use Symfony\AI\Platform\Cost\PriceTable;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class RunBudgetTest extends TestCase
{
    public function testRunIsStoppedOnceTheTokenBudgetIsSpent()
    {
        $guardrails = [];
        $invocations = 0;
        $dispatcher = new EventDispatcher();
        $agent = $this->createLoopingAgent(new RunBudget(maxTokens: 250, eventDispatcher: $dispatcher), $dispatcher, $guardrails, $invocations);

        $this->assertStringContainsString('budget', $agent->call('Loop forever')->getContent());
        $this->assertSame(3, $invocations);
        $this->assertCount(1, $guardrails);
        $this->assertSame('run_budget', $guardrails[0]->getGuardrail());
        $this->assertSame(GuardrailTriggered::ACTION_STOPPED, $guardrails[0]->getAction());
        $this->assertSame(300, $guardrails[0]->getDetails()['tokens']);
    }

    public function testRunIsStoppedOnceTheCostBudgetIsSpent()
    {
        $guardrails = [];
        $invocations = 0;
        $dispatcher = new EventDispatcher();
        $calculator = new CostCalculator(PriceTable::fromArray(['gpt-4o' => ['input' => 1000.0, 'output' => 1000.0]]));
        $agent = $this->createLoopingAgent(new RunBudget(maxCost: 0.15, costCalculator: $calculator, eventDispatcher: $dispatcher), $dispatcher, $guardrails, $invocations);

        $agent->call('Loop forever')->getContent();

        // every round costs 0.1, the second one exceeds the budget
        $this->assertSame(2, $invocations);
        $this->assertEqualsWithDelta(0.2, $guardrails[0]->getDetails()['cost'], 0.0001);
    }

    public function testSpendingIsKeptPerRun()
    {
        $guardrails = [];
        $invocations = 0;
        $dispatcher = new EventDispatcher();
        $budget = new RunBudget(maxTokens: 250, eventDispatcher: $dispatcher);
        $agent = $this->createLoopingAgent($budget, $dispatcher, $guardrails, $invocations);

        $first = $agent->call('Loop forever');
        $second = $agent->call('Loop forever');
        $first->getContent();
        $second->getContent();

        $this->assertSame(6, $invocations);
        $this->assertCount(2, $guardrails);
        $this->assertNotSame($guardrails[0]->getRunContext(), $guardrails[1]->getRunContext());

        $runContext = $first->getResult()->getMetadata()->get('run_context');
        $this->assertInstanceOf(RunContext::class, $runContext);
        $this->assertSame(300, $budget->getSpent($runContext)['tokens'] ?? null);
    }

    public function testBudgetAsInputProcessorOnlyLimitsTheRunsOfItsAgent()
    {
        $guardrails = [];
        $invocations = 0;
        $dispatcher = new EventDispatcher();
        $budget = new RunBudget(maxTokens: 250, eventDispatcher: $dispatcher, onlyProcessedRuns: true);
        $limited = $this->createLoopingAgent($budget, $dispatcher, $guardrails, $invocations, [$budget]);
        $unlimited = new Agent(MockPlatformFactory::createPlatform(static function () use (&$invocations): ResultInterface {
            $result = 0 === $invocations++ ? new ToolCallResult([new ToolCall('call-other', 'tool_no_params')]) : new TextResult('Done.');
            $result->getMetadata()->add('token_usage', new TokenUsage(totalTokens: 1000));

            return $result;
        }), 'gpt-4o', toolbox: new Toolbox([new ToolNoParams()]), eventDispatcher: $dispatcher);

        $this->assertSame('Done.', $unlimited->call('Hi')->getContent());
        $this->assertCount(0, $guardrails);

        $limited->call('Loop forever')->getContent();
        $this->assertCount(1, $guardrails);
    }

    /**
     * @param list<GuardrailTriggered> $guardrails
     * @param list<RunBudget>          $inputProcessors
     */
    private function createLoopingAgent(RunBudget $budget, EventDispatcher $dispatcher, array &$guardrails, int &$invocations, array $inputProcessors = []): Agent
    {
        $dispatcher->addListener(ToolCallsExecuted::class, $budget);
        $dispatcher->addListener(GuardrailTriggered::class, static function (GuardrailTriggered $event) use (&$guardrails): void {
            $guardrails[] = $event;
        });

        $platform = MockPlatformFactory::createPlatform(static function () use (&$invocations): ResultInterface {
            ++$invocations;
            $result = new ToolCallResult([new ToolCall('call-'.$invocations, 'tool_no_params')]);
            $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 50, completionTokens: 50, totalTokens: 100));

            return $result;
        });

        return new Agent($platform, 'gpt-4o', $inputProcessors, toolbox: new Toolbox([new ToolNoParams()]), eventDispatcher: $dispatcher);
    }
}
