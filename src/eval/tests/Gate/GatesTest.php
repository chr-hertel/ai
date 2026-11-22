<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Gate;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\Gate\CostPerCaseGate;
use Symfony\AI\Eval\Gate\PassRateGate;
use Symfony\AI\Eval\Gate\RegressionGate;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\Report\CaseResult;
use Symfony\AI\Eval\Report\GateResult;
use Symfony\AI\Eval\Report\Report;
use Symfony\AI\Eval\Score;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

final class GatesTest extends TestCase
{
    public function testRegressionGateFailsWithoutBaseline()
    {
        $results = (new RegressionGate('baseline', 0.02))->check($this->report(['candidate' => true]));

        $this->assertCount(1, $results);
        $this->assertSame('candidate', $results[0]->getVariant());
        $this->assertFalse($results[0]->isPassed());
        $this->assertStringContainsString('baseline "baseline" was not run', $results[0]->getMessage());
    }

    public function testRegressionGateFailsWithoutVariantToCompare()
    {
        $results = (new RegressionGate('baseline', 0.02))->check($this->report(['baseline' => true]));

        $this->assertCount(1, $results);
        $this->assertSame('baseline', $results[0]->getVariant());
        $this->assertFalse($results[0]->isPassed());
    }

    public function testRegressionGateComparesWithBaseline()
    {
        $results = (new RegressionGate('baseline', 0.02))->check($this->report(['baseline' => true, 'candidate' => false]));

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->isPassed());
        $this->assertStringContainsString('pass rate dropped 100.0 points vs baseline', $results[0]->getMessage());
    }

    public function testRegressionGateFailsIfVariantHasNoResults()
    {
        $report = new Report('suite', [$this->caseResult('baseline', true)], ['baseline', 'candidate']);

        $results = (new RegressionGate('baseline', 0.02))->check($report);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->isPassed());
        $this->assertStringContainsString('no results for candidate', $results[0]->getMessage());
    }

    public function testPassRateGateSkipsConfiguredVariantsThatWereNotSelected()
    {
        $results = (new PassRateGate(0.9, variants: ['candidate', 'cheap']))->check($this->report(['baseline' => false, 'candidate' => true]));

        $this->assertCount(1, $results);
        $this->assertSame('candidate', $results[0]->getVariant());
        $this->assertTrue($results[0]->isPassed());
    }

    public function testPassRateGateFailsIfNoneOfItsVariantsWasRun()
    {
        $results = (new PassRateGate(0.9, variants: ['candidate', 'cheap']))->check($this->report(['baseline' => true]));

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->isPassed());
        $this->assertStringContainsString('none of the variants "candidate", "cheap" was run', $results[0]->getMessage());
    }

    public function testPassRateGateFailsWithoutCasesOfTheLabel()
    {
        $results = (new PassRateGate(0.9, 'refund'))->check($this->report(['candidate' => true]));

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->isPassed());
        $this->assertStringContainsString('no case with the label "refund"', $results[0]->getMessage());
    }

    public function testPassRateGateFailsForVariantWithoutResults()
    {
        $results = (new PassRateGate(0.9))->check(new Report('suite', [], ['candidate']));

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->isPassed());
    }

    public function testCostPerCaseGateFailsWithoutCost()
    {
        $results = (new CostPerCaseGate(0.05))->check($this->report(['candidate' => true]));

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->isPassed());
        $this->assertStringContainsString('the cost is unknown', $results[0]->getMessage());
    }

    public function testReportWithoutFailingGatesPasses()
    {
        $report = $this->report(['candidate' => true]);
        $report->addGateResults(new GateResult('pass_rate', 'candidate', true, 'ok'));

        $this->assertTrue($report->isPassed());
    }

    /**
     * @param array<string, bool> $passedByVariant
     */
    private function report(array $passedByVariant): Report
    {
        $results = [];
        foreach ($passedByVariant as $variant => $passed) {
            $results[] = $this->caseResult($variant, $passed);
        }

        return new Report('suite', $results, array_keys($passedByVariant));
    }

    private function caseResult(string $variant, bool $passed): CaseResult
    {
        $run = new RecordedRun(new MessageBag(Message::ofUser('Hi')), new TextResult('Hello'));
        $score = $passed ? Score::pass('text') : Score::fail('text', 'wrong');

        return new CaseResult($variant, new EvalCase('greeting', 'Hi'), 1, $run, [$score]);
    }
}
