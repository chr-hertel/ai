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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand('ai:eval:dataset:add', 'Adds an item to a Langfuse dataset')]
final class DatasetAddCommand extends Command
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
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'The user message, or the conversation as JSON list, the input of the source trace by default')
            ->addOption('expect', null, InputOption::VALUE_REQUIRED, 'The expected output, expectations as JSON object or a reference answer')
            ->addOption('label', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Labels of the case')
            ->addOption('metadata', null, InputOption::VALUE_REQUIRED, 'Metadata of the item as JSON object')
            ->addOption('source-trace-id', null, InputOption::VALUE_REQUIRED, 'The trace the item is taken from, e.g. a production run with a thumbs down')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'The ID of the item, an item with the same ID is updated')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command adds an item to a Langfuse dataset. A production trace becomes a test case:

                  <info>php %command.full_name% support-regressions --source-trace-id=4bf92f3577b34da6a3ce929d0e0e4736 --expect='{"tools_called":["order_lookup"]}' --label=refund</info>
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');

        $caseInput = $input->getOption('input');
        if (\is_string($caseInput)) {
            $conversation = json_decode($caseInput, true);
            $caseInput = \is_array($conversation) && array_is_list($conversation) ? $conversation : $caseInput;
        }

        $expect = $input->getOption('expect');
        if (\is_string($expect)) {
            $expectations = json_decode($expect, true);
            $expect = \is_array($expectations) ? $expectations : $expect;
        }

        $metadata = [];
        if (null !== $input->getOption('metadata')) {
            $metadata = json_decode((string) $input->getOption('metadata'), true);
            if (!\is_array($metadata) || ([] !== $metadata && array_is_list($metadata))) {
                throw new InvalidArgumentException('The metadata must be a JSON object.');
            }
        }

        if ([] !== $labels = $input->getOption('label')) {
            $metadata['labels'] = $labels;
        }

        $id = $this->source->addItem($name, $caseInput, $expect, $metadata, $input->getOption('source-trace-id'), $input->getOption('id'));

        $output->writeln(\sprintf('Added the item "%s" to the dataset "%s"', $id, $name));

        return Command::SUCCESS;
    }
}
