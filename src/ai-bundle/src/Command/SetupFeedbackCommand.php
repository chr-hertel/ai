<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Command;

use Symfony\AI\Feedback\Bridge\Doctrine\DoctrineRecorder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand('ai:feedback:setup', 'Creates the database table of the Doctrine feedback recorder')]
final class SetupFeedbackCommand extends Command
{
    public function __construct(
        private readonly DoctrineRecorder $recorder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->recorder->setup();

        (new SymfonyStyle($input, $output))->success('The feedback table is set up.');

        return Command::SUCCESS;
    }
}
