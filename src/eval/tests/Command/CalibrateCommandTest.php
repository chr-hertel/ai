<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Eval\Calibration\LabeledRun;
use Symfony\AI\Eval\Command\CalibrateCommand;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\EvaluatorInterface;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\Score;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CalibrateCommandTest extends TestCase
{
    public function testPrintsTheAgreementAndChecksTheMinimumKappa()
    {
        $tester = new CommandTester($this->command());

        $this->assertSame(Command::SUCCESS, $tester->execute(['judge' => 'ticket', 'runs' => 'support']));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Judge ticket on 6 labeled runs', $display);
        $this->assertStringContainsString('66.7 %', $display);
        $this->assertStringContainsString('0.33', $display);
        $this->assertStringContainsString('e: human fail, judge pass', $display);
        $this->assertStringContainsString('f: human pass, judge fail (No ticket.)', $display);

        $this->assertSame(Command::SUCCESS, $tester->execute(['judge' => 'ticket', 'runs' => 'support', '--min-kappa' => '0.3']));
        $this->assertStringContainsString('PASS kappa 0.33 >= 0.30', $tester->getDisplay());

        $this->assertSame(Command::FAILURE, $tester->execute(['judge' => 'ticket', 'runs' => 'support', '--min-kappa' => '0.6']));
        $this->assertStringContainsString('FAIL kappa 0.33 < 0.60', $tester->getDisplay());
    }

    public function testFailsTheMinimumKappaWithoutScoredRuns()
    {
        $tester = new CommandTester($this->command());

        $this->assertSame(Command::FAILURE, $tester->execute(['judge' => 'ticket', 'runs' => 'empty', '--min-kappa' => '0.6']));
        $this->assertStringContainsString('the judge scored none of the labeled runs', $tester->getDisplay());
    }

    public function testUnknownJudge()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The judge "tone" does not exist, available judges: "ticket".');

        (new CommandTester($this->command()))->execute(['judge' => 'tone', 'runs' => 'support']);
    }

    public function testUnknownLabeledRuns()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The labeled runs "billing" do not exist, available sets: "support", "empty".');

        (new CommandTester($this->command()))->execute(['judge' => 'ticket', 'runs' => 'billing']);
    }

    public function testInvalidMinimumKappa()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The minimum kappa "high" is not a number.');

        (new CommandTester($this->command()))->execute(['judge' => 'ticket', 'runs' => 'support', '--min-kappa' => 'high']);
    }

    private function command(): CalibrateCommand
    {
        $judge = new class implements EvaluatorInterface {
            public function getName(): string
            {
                return 'ticket';
            }

            public function evaluate(EvalCase $case, RecordedRun $run): Score
            {
                return str_contains((string) $run->getAnswer(), 'ticket') ? Score::pass('ticket') : Score::fail('ticket', 'No ticket.');
            }
        };

        return new CalibrateCommand([$judge], [
            'support' => [
                $this->labeled('a', 'Your ticket is RF-1.', true),
                $this->labeled('b', 'Your ticket is RF-2.', true),
                $this->labeled('c', 'Refund processed.', false),
                $this->labeled('d', 'Refund processed.', false),
                $this->labeled('e', 'A ticket will follow, refund processed.', false),
                $this->labeled('f', 'Opened RF-3 for you.', true),
            ],
            'empty' => [],
        ]);
    }

    private function labeled(string $id, string $answer, bool $human): LabeledRun
    {
        return new LabeledRun(new EvalCase($id, ''), new RecordedRun(new MessageBag(), new TextResult($answer)), $human);
    }
}
