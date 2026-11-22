<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Calibration;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Eval\Calibration\JudgeCalibrator;
use Symfony\AI\Eval\Calibration\LabeledRun;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\EvaluatorInterface;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\Score;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

final class JudgeCalibratorTest extends TestCase
{
    public function testAgreementAndKappa()
    {
        // the judge passes every answer containing "ticket"
        $judge = new class implements EvaluatorInterface {
            public function getName(): string
            {
                return 'judge';
            }

            public function evaluate(EvalCase $case, RecordedRun $run): Score
            {
                return str_contains((string) $run->getAnswer(), 'ticket') ? Score::pass('judge') : Score::fail('judge', 'No ticket.');
            }
        };

        $labeled = [
            $this->labeled('a', 'Your ticket is RF-1.', true),
            $this->labeled('b', 'Your ticket is RF-2.', true),
            $this->labeled('c', 'Refund processed.', false),
            $this->labeled('d', 'Refund processed.', false),
            $this->labeled('e', 'A ticket will follow, refund processed.', false),
            $this->labeled('f', 'Opened RF-3 for you.', true),
        ];

        $report = (new JudgeCalibrator())->calibrate($judge, $labeled);

        $this->assertSame(6, $report->getTotal());
        $this->assertEqualsWithDelta(4 / 6, $report->getAgreement(), 0.0001);
        $this->assertEqualsWithDelta(1 / 6, $report->getFalsePassRate(), 0.0001);
        $this->assertEqualsWithDelta(1 / 6, $report->getFalseFailRate(), 0.0001);
        $this->assertEqualsWithDelta(1 / 3, $report->getKappa(), 0.0001);
        $this->assertSame(['e', 'f'], array_column($report->getDisagreements(), 'case'));
    }

    private function labeled(string $id, string $answer, bool $human): LabeledRun
    {
        return new LabeledRun(new EvalCase($id, ''), new RecordedRun(new MessageBag(), new TextResult($answer)), $human);
    }
}
