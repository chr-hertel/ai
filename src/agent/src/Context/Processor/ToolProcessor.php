<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context\Processor;

use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Exposes the agent's tools to the platform invocation via the "tools" option.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolProcessor implements ContextProcessorInterface
{
    public function __construct(
        private readonly ToolboxInterface $toolbox,
    ) {
    }

    public static function supportedTypes(): array
    {
        return [];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
        $toolMap = $this->toolbox->getTools();
        if ([] === $toolMap) {
            return;
        }

        $options = $request->getOptions();

        // only filter tool map if a list of strings is provided as option
        if (isset($options['tools']) && \is_array($options['tools']) && $this->isFlatStringArray($options['tools'])) {
            $toolMap = array_values(array_filter($toolMap, static fn (Tool $tool): bool => \in_array($tool->getName(), $options['tools'], true)));
        }

        $options['tools'] = $toolMap;
        $request->setOptions($options);
    }

    /**
     * @param array<mixed> $tools
     */
    private function isFlatStringArray(array $tools): bool
    {
        return array_reduce($tools, static fn (bool $carry, mixed $item): bool => $carry && \is_string($item), true);
    }
}
