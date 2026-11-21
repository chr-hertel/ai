<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Bridge\Langfuse\Command;

use Symfony\AI\Eval\Bridge\Langfuse\LangfuseDatasetSource;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\Exception\RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand('ai:eval:dataset:pull', 'Writes a Langfuse dataset to a dataset file')]
final class DatasetPullCommand extends Command
{
    public function __construct(
        private readonly LangfuseDatasetSource $source,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Name of the Langfuse dataset')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path of the dataset file, "evals/datasets/<name>.yaml" by default')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite an existing dataset file')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command writes the active items of a Langfuse dataset to a YAML dataset file:

                  <info>php %command.full_name% support-regressions evals/datasets/support-regressions.yaml</info>
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!class_exists(Yaml::class)) {
            throw new RuntimeException('For writing dataset files, the symfony/yaml package is required. Try running "composer require symfony/yaml".');
        }

        $name = (string) $input->getArgument('name');
        $path = (string) ($input->getArgument('path') ?? \sprintf('evals/datasets/%s.yaml', $name));

        if (file_exists($path) && !$input->getOption('force')) {
            throw new InvalidArgumentException(\sprintf('The dataset file "%s" already exists, use --force to overwrite it.', $path));
        }

        $dataset = $this->source->fetch($name);

        $directory = \dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(\sprintf('The directory of "%s" cannot be created.', $path));
        }

        if (false === @file_put_contents($path, Yaml::dump($dataset->toArray(), 8, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK))) {
            throw new RuntimeException(\sprintf('The dataset file "%s" cannot be written.', $path));
        }

        $output->writeln(\sprintf('Wrote %d cases of the dataset "%s" to %s', \count($dataset), $name, $path));

        return Command::SUCCESS;
    }
}
