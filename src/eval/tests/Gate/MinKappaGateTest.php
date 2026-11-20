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
use Symfony\AI\Eval\Calibration\LabeledRun;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\EvaluatorInterface;
use Symfony\AI\Eval\Gate\MinKappaGate;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\Report\GateResult;
use Symfony\AI\Eval\Report\Report;
use Symfony\AI\Eval\Score;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

final class MinKappaGateTest extends TestCase
{
    public function testPassesIfTheJudgeAgreesWithTheLabels()
    {
        $gate = new MinKappaGate($this->judge(), [$this->labeled('a', 'ticket RF-1', true), $this->labeled('b', 'no refund', false)], 0.6);

        $this->assertEquals([new GateResult('min_kappa', 'judge', true, 'kappa 1.00 >= 0.60')], $gate->check(new Report('support', [], ['default'])));
    }

    public function testFailsBelowTheMinimum()
    {
        $labeled = [
            $this->labeled('a', 'ticket RF-1', true),
            $this->labeled('b', 'ticket RF-2', true),
            $this->labeled('c', 'no refund', false),
            $this->labeled('d', 'no refund', false),
            $this->labeled('e', 'ticket follows', false),
            $this->labeled('f', 'opened RF-3', true),
        ];
        $gate = new MinKappaGate($this->judge(), $labeled, 0.6);

        $this->assertEquals([new GateResult('min_kappa', 'judge', false, 'kappa 0.33 < 0.60')], $gate->check(new Report('support', [], ['default'])));
    }

    public function testFailsWithoutLabeledRuns()
    {
        $gate = new MinKappaGate($this->judge(), [], 0.6);

        $this->assertEquals([new GateResult('min_kappa', 'judge', false, 'kappa not checked, the judge scored none of the labeled runs')], $gate->check(new Report('support', [], ['default'])));
    }

    private function judge(): EvaluatorInterface
    {
        return new class implements EvaluatorInterface {
            public function getName(): string
            {
                return 'judge';
            }

            public function evaluate(EvalCase $case, RecordedRun $run): Score
            {
                return str_contains((string) $run->getAnswer(), 'ticket') ? Score::pass('judge') : Score::fail('judge', 'No ticket.');
            }
        };
    }

    private function labeled(string $id, string $answer, bool $human): LabeledRun
    {
        return new LabeledRun(new EvalCase($id, ''), new RecordedRun(new MessageBag(), new TextResult($answer)), $human);
    }
}
