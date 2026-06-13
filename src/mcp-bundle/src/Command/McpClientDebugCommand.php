<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Command;

use Mcp\Client;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Connects to a configured MCP client and prints what the remote server advertises.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand('mcp:client:debug', 'Inspect a configured MCP client (server info, tools, resources, prompts)')]
final class McpClientDebugCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $clients,
    ) {
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues($this->getClientNames());
        }
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Name of the configured client (under mcp.clients.<name>)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');

        if (!$this->clients->has($name)) {
            $known = $this->getClientNames();
            $io->error(\sprintf(
                'No MCP client named "%s" is configured.%s',
                $name,
                [] === $known ? '' : \sprintf(' Available: %s.', implode(', ', $known)),
            ));

            return Command::INVALID;
        }

        $client = $this->clients->get($name);
        if (!$client instanceof Client) {
            $io->error(\sprintf('Service for client "%s" is not an instance of %s.', $name, Client::class));

            return Command::FAILURE;
        }

        $io->title(\sprintf('MCP client "%s"', $name));

        try {
            $tools = $client->listTools();
        } catch (\Throwable $e) {
            $io->error(\sprintf('Failed to connect to or query the server: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $serverInfo = $client->getServerInfo();
        if (null !== $serverInfo) {
            $io->section('Server');
            $io->definitionList(
                ['Name' => $serverInfo->name],
                ['Version' => $serverInfo->version],
            );
        }

        if (null !== ($instructions = $client->getInstructions())) {
            $io->section('Instructions');
            $io->writeln($instructions);
        }

        $io->section(\sprintf('Tools (%d)', \count($tools->tools)));
        if ([] === $tools->tools) {
            $io->writeln('<comment>No tools advertised by the server.</comment>');
        } else {
            $table = new Table($output);
            $table->setHeaders(['Name', 'Description', 'Required arguments']);
            foreach ($tools->tools as $tool) {
                $table->addRow([
                    $tool->name,
                    $tool->description ?? '',
                    implode(', ', $tool->inputSchema['required'] ?? []),
                ]);
            }
            $table->render();
        }

        $client->disconnect();

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function getClientNames(): array
    {
        if (!method_exists($this->clients, 'getProvidedServices')) {
            return [];
        }

        /** @var array<string, string> $services */
        $services = $this->clients->getProvidedServices();

        return array_keys($services);
    }
}
