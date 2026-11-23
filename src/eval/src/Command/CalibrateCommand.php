<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Command;

use Symfony\AI\Eval\Calibration\JudgeCalibrator;
use Symfony\AI\Eval\Calibration\LabeledRun;
use Symfony\AI\Eval\EvaluatorInterface;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand('ai:eval:calibrate', 'Measures how well a judge agrees with human labels')]
final class CalibrateCommand extends Command
{
    /**
     * @param iterable<EvaluatorInterface>           $judges
     * @param iterable<string, iterable<LabeledRun>> $labeledRuns the human-labeled runs, keyed by the name of the set
     */
    public function __construct(
        private readonly iterable $judges,
        private readonly iterable $labeledRuns,
        private readonly JudgeCalibrator $calibrator = new JudgeCalibrator(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('judge', InputArgument::REQUIRED, 'Name of the judge')
            ->addArgument('runs', InputArgument::REQUIRED, 'Name of the set of labeled runs')
            ->addOption('min-kappa', null, InputOption::VALUE_REQUIRED, 'Exit with 1 if Cohen\'s kappa is below this value, e.g. 0.6')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command runs a judge on runs a human labeled and compares the verdicts:

                  <info>php %command.full_name% helpfulness support-labeled --min-kappa=0.6</info>
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $judge = $this->judge((string) $input->getArgument('judge'));
        $runs = $this->runs((string) $input->getArgument('runs'));

        $minKappa = $input->getOption('min-kappa');
        if (null !== $minKappa && !is_numeric($minKappa)) {
            throw new InvalidArgumentException(\sprintf('The minimum kappa "%s" is not a number.', $minKappa));
        }

        $report = $this->calibrator->calibrate($judge, $runs);

        $output->writeln(\sprintf('Judge %s on %d labeled runs', $report->getEvaluator(), $report->getTotal()));
        $output->writeln('');
        $output->writeln(\sprintf(' Agreement        %5.1f %%', $report->getAgreement() * 100));
        $output->writeln(\sprintf(' Cohen\'s kappa    %6.2f', $report->getKappa()));
        $output->writeln(\sprintf(' False pass rate  %5.1f %%', $report->getFalsePassRate() * 100));
        $output->writeln(\sprintf(' False fail rate  %5.1f %%', $report->getFalseFailRate() * 100));

        if ([] !== $report->getDisagreements()) {
            $output->writeln('');
            $output->writeln(' Disagreements:');
            foreach ($report->getDisagreements() as $disagreement) {
                $output->writeln(\sprintf(
                    '  - %s: human %s, judge %s%s',
                    $disagreement['case'],
                    $disagreement['human'] ? 'pass' : 'fail',
                    $disagreement['judge'] ? 'pass' : 'fail',
                    null === $disagreement['explanation'] ? '' : ' ('.$disagreement['explanation'].')',
                ));
            }
        }

        if (null === $minKappa) {
            return Command::SUCCESS;
        }

        $output->writeln('');
        if (0 === $report->getTotal()) {
            $output->writeln(' FAIL kappa not checked, the judge scored none of the labeled runs');

            return Command::FAILURE;
        }

        $passed = $report->getKappa() >= (float) $minKappa;
        $output->writeln(\sprintf(' %s kappa %.2f %s %.2f', $passed ? 'PASS' : 'FAIL', $report->getKappa(), $passed ? '>=' : '<', (float) $minKappa));

        return $passed ? Command::SUCCESS : Command::FAILURE;
    }

    private function judge(string $name): EvaluatorInterface
    {
        $names = [];
        foreach ($this->judges as $judge) {
            if ($judge->getName() === $name) {
                return $judge;
            }

            $names[] = $judge->getName();
        }

        throw new InvalidArgumentException(\sprintf('The judge "%s" does not exist, available judges: "%s".', $name, implode('", "', $names)));
    }

    /**
     * @return iterable<LabeledRun>
     */
    private function runs(string $name): iterable
    {
        $names = [];
        foreach ($this->labeledRuns as $setName => $runs) {
            if ((string) $setName === $name) {
                return $runs;
            }

            $names[] = (string) $setName;
        }

        throw new InvalidArgumentException(\sprintf('The labeled runs "%s" do not exist, available sets: "%s".', $name, implode('", "', $names)));
    }
}
