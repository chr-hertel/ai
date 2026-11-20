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

use Symfony\AI\Eval\EvalRunner;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\Exception\RuntimeException;
use Symfony\AI\Eval\Report\CaseResult;
use Symfony\AI\Eval\Report\Formatter\FormatterInterface;
use Symfony\AI\Eval\Report\Formatter\JsonFormatter;
use Symfony\AI\Eval\Report\Formatter\JunitFormatter;
use Symfony\AI\Eval\Report\Formatter\MarkdownFormatter;
use Symfony\AI\Eval\Report\Formatter\TextFormatter;
use Symfony\AI\Eval\Suite;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand('ai:eval:run', 'Runs an eval suite and checks its gates')]
final class RunCommand extends Command
{
    /**
     * @param iterable<Suite> $suites
     */
    public function __construct(
        private readonly iterable $suites,
        private readonly EvalRunner $runner = new EvalRunner(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('suite', InputArgument::REQUIRED, 'Name of the suite')
            ->addOption('variant', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only run these variants')
            ->addOption('report', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Write a report as format:path, formats are json, junit, markdown and text')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command runs every case of a suite for every variant and exits with 1 if a gate fails:

                  <info>php %command.full_name% support --variant=baseline --variant=candidate --report=junit:var/eval/junit.xml</info>
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $suite = $this->suite((string) $input->getArgument('suite'));
        $reports = $this->reports($input->getOption('report'));

        $report = $this->runner->run($suite, $input->getOption('variant'), static function (CaseResult $result) use ($output): void {
            $output->write($result->isPassed() ? '.' : 'F');
        });
        $output->writeln("\n");

        $output->write((new TextFormatter())->format($report));

        foreach ($reports as [$format, $path, $formatter]) {
            if (false === @file_put_contents($path, $formatter->format($report))) {
                throw new RuntimeException(\sprintf('The %s report cannot be written to "%s".', $format, $path));
            }

            $output->writeln(\sprintf(' Wrote the %s report to %s', $format, $path));
        }

        return $report->isPassed() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Resolved before the run, so a typo does not waste a paid run.
     *
     * @param list<string> $options
     *
     * @return list<array{string, string, FormatterInterface}>
     */
    private function reports(array $options): array
    {
        $reports = [];
        foreach ($options as $option) {
            [$format, $path] = explode(':', $option, 2) + [1 => ''];
            if ('' === $path) {
                throw new InvalidArgumentException(\sprintf('The report option "%s" must be given as format:path.', $option));
            }

            $formatter = $this->formatter($format);

            $directory = \dirname($path);
            if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new InvalidArgumentException(\sprintf('The directory of "%s" cannot be created.', $path));
            }

            if (!is_writable($directory) || (file_exists($path) && !is_writable($path))) {
                throw new InvalidArgumentException(\sprintf('The report file "%s" is not writable.', $path));
            }

            $reports[] = [$format, $path, $formatter];
        }

        return $reports;
    }

    private function suite(string $name): Suite
    {
        $names = [];
        foreach ($this->suites as $suite) {
            if ($suite->getName() === $name) {
                return $suite;
            }

            $names[] = $suite->getName();
        }

        throw new InvalidArgumentException(\sprintf('The suite "%s" does not exist, available suites: "%s".', $name, implode('", "', $names)));
    }

    private function formatter(string $format): FormatterInterface
    {
        return match ($format) {
            'json' => new JsonFormatter(),
            'junit' => new JunitFormatter(),
            'markdown' => new MarkdownFormatter(),
            'text' => new TextFormatter(),
            default => throw new InvalidArgumentException(\sprintf('The report format "%s" is not supported.', $format)),
        };
    }
}
