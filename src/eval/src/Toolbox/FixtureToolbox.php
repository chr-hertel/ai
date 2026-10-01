<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Toolbox;

use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolCatalogInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Exposes the real tool definitions to the model, but answers tool calls with the recorded fixtures of an eval case.
 *
 * Evals stay hermetic this way: they never open a real refund. A fixture is either a scalar or list returned for
 * every call of the tool, or a map from the value of the call's first argument to the result, with "*" as fallback:
 *
 *     fixtures:
 *         order_lookup:
 *             SO-10023: { status: delivered }
 *         open_refund: 'RF-4711'
 *         shop_info:
 *             '*': { opening_hours: '9-17' }
 *
 * Calls without a fixture are answered with a message telling the model the lookup failed.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FixtureToolbox implements ToolboxInterface
{
    /**
     * @param array<string, mixed> $fixtures
     */
    public function __construct(
        private readonly ToolCatalogInterface $catalog,
        private readonly array $fixtures,
    ) {
    }

    public function getTools(): array
    {
        return $this->catalog->getTools();
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        $names = array_map(static fn (Tool $tool): string => $tool->getName(), $this->getTools());
        if (!\in_array($toolCall->getName(), $names, true)) {
            throw ToolNotFoundException::notFoundForToolCall($toolCall);
        }

        if (!\array_key_exists($toolCall->getName(), $this->fixtures)) {
            return new ToolResult($toolCall, \sprintf('No result available for tool "%s".', $toolCall->getName()));
        }

        $fixture = $this->fixtures[$toolCall->getName()];

        if (!\is_array($fixture) || array_is_list($fixture)) {
            return new ToolResult($toolCall, $fixture);
        }

        $arguments = $toolCall->getArguments();
        $key = [] === $arguments ? null : reset($arguments);

        if (\is_scalar($key) && \array_key_exists((string) $key, $fixture)) {
            return new ToolResult($toolCall, $fixture[(string) $key]);
        }

        if (\array_key_exists('*', $fixture)) {
            return new ToolResult($toolCall, $fixture['*']);
        }

        return new ToolResult($toolCall, \sprintf('Nothing found for "%s".', \is_scalar($key) ? $key : json_encode($arguments)));
    }
}
