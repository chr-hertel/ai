<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Eval\Dataset;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\EvalRunner;
use Symfony\AI\Eval\Evaluator\Limits;
use Symfony\AI\Eval\Evaluator\TextAssertions;
use Symfony\AI\Eval\Evaluator\ToolArguments;
use Symfony\AI\Eval\Evaluator\ToolsCalled;
use Symfony\AI\Eval\Gate\CostPerCaseGate;
use Symfony\AI\Eval\Gate\PassRateGate;
use Symfony\AI\Eval\Gate\RegressionGate;
use Symfony\AI\Eval\Report\Formatter\JsonFormatter;
use Symfony\AI\Eval\Report\Formatter\JunitFormatter;
use Symfony\AI\Eval\Report\Formatter\MarkdownFormatter;
use Symfony\AI\Eval\Report\Formatter\TextFormatter;
use Symfony\AI\Eval\Suite;
use Symfony\AI\Eval\Tests\Fixtures\SupportModel;
use Symfony\AI\Eval\Tests\Fixtures\SupportTools;
use Symfony\AI\Eval\Variant;
use Symfony\AI\Platform\Cost\CostCalculator;
use Symfony\AI\Platform\Cost\PriceTable;
use Symfony\AI\Platform\Test\InMemoryPlatform;

final class EvalRunnerTest extends TestCase
{
    public function testSuiteComparesVariantsAndChecksGates()
    {
        $report = (new EvalRunner(new CostCalculator(PriceTable::fromArray(['support-model' => ['input' => 1.0, 'output' => 5.0]]))))->run($this->suite());

        $this->assertSame(['baseline', 'candidate'], $report->getVariants());
        $this->assertSame(0.5, $report->getPassRate('baseline'));
        $this->assertSame(1.0, $report->getPassRate('candidate'));
        $this->assertSame(0.0, $report->getPassRate('baseline', 'refund'));
        $this->assertSame(1.0, $report->getPassRate('baseline', 'escalation'));
        $this->assertSame([], $report->getFlakyCases('candidate'));
        $this->assertNotNull($report->getAverageCost('candidate'));

        $gates = [];
        foreach ($report->getGateResults() as $gate) {
            $gates[$gate->getGate().'/'.$gate->getVariant()] = $gate->isPassed();
        }

        $this->assertSame([
            'pass_rate/baseline' => false,
            'pass_rate/candidate' => true,
            'regression/candidate' => true,
            'cost_per_case/baseline' => true,
            'cost_per_case/candidate' => true,
        ], $gates);
        $this->assertFalse($report->isPassed());

        $failure = $report->getFailures('baseline')[0];
        $this->assertSame('refund-without-ticket-001', $failure->getCase()->getId());
        $this->assertStringContainsString('tools_called', implode(' ', $failure->getFailureReasons()));
        $this->assertNotNull($failure->getRun()->getRunId());
    }

    public function testOnlySelectedVariantsAreRun()
    {
        $report = (new EvalRunner())->run($this->suite(), ['candidate']);

        $this->assertSame(['candidate'], $report->getVariants());
        $this->assertTrue($report->isPassed());
    }

    public function testFormatters()
    {
        $report = (new EvalRunner())->run($this->suite());

        $text = (new TextFormatter())->format($report);
        $this->assertStringContainsString('candidate', $text);
        $this->assertStringContainsString('FAIL baseline', $text);

        $this->assertStringContainsString('| candidate | 100.0 %', (new MarkdownFormatter())->format($report));
        $this->assertStringContainsString('source run `4bf92f3577b34da6a3ce929d0e0e4736`', (new MarkdownFormatter())->format($report));

        $json = json_decode((new JsonFormatter())->format($report), true);
        $this->assertFalse($json['passed']);
        $this->assertEquals(1.0, $json['variants']['candidate']['pass_rate']);
        $this->assertSame(['order_lookup', 'open_refund'], $json['results'][2]['tools']);

        $junit = simplexml_load_string((new JunitFormatter())->format($report));
        $this->assertNotFalse($junit);
        $this->assertSame('1', (string) $junit->testsuite[0]['failures']);
        $this->assertCount(1, $junit->xpath('//testcase[@name="pass_rate"]/failure'));
    }

    private function suite(): Suite
    {
        $prompts = [
            'baseline' => 'You are the support assistant.',
            'candidate' => 'You are the support assistant. Never promise a refund before open_refund returned a ticket id.',
        ];

        return new Suite(
            'support',
            [Dataset::fromFile(__DIR__.'/Fixtures/datasets/support.yaml')],
            [new Variant('baseline', ['model' => 'support-model']), new Variant('candidate', ['model' => 'support-model'])],
            static fn (Variant $variant, ?ToolboxInterface $toolbox, EvalCase $case): Agent => new Agent(
                new InMemoryPlatform(\Closure::fromCallable(new SupportModel())),
                $variant->get('model'),
                instruction: $prompts[$variant->getName()],
                toolbox: $toolbox,
            ),
            [new ToolsCalled(), new ToolArguments(), new TextAssertions(), new Limits()],
            [new PassRateGate(0.9), new RegressionGate('baseline', 0.02), new CostPerCaseGate(0.01)],
            toolCatalog: new Toolbox([new SupportTools()]),
        );
    }
}
