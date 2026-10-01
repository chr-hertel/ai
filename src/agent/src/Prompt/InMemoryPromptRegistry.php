<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Prompt;

use Symfony\AI\Agent\Exception\InvalidArgumentException;

/**
 * Holds prompt versions in memory, versions are expected in chronological order.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InMemoryPromptRegistry implements PromptRegistryInterface
{
    /**
     * @param array<string, array<string, string>> $prompts templates keyed by prompt name and version
     */
    public function __construct(
        private readonly array $prompts = [],
    ) {
    }

    public function get(string $name, ?string $version = null): Prompt
    {
        $versions = $this->versions($name);
        $version ??= $versions[array_key_last($versions)];

        if (!isset($this->prompts[$name][$version])) {
            throw new InvalidArgumentException(\sprintf('Prompt "%s" has no version "%s", available versions: "%s".', $name, $version, implode('", "', $versions)));
        }

        return new Prompt($name, $version, $this->prompts[$name][$version]);
    }

    public function versions(string $name): array
    {
        if (!isset($this->prompts[$name]) || [] === $this->prompts[$name]) {
            throw new InvalidArgumentException(\sprintf('Prompt "%s" does not exist.', $name));
        }

        return array_map('strval', array_keys($this->prompts[$name]));
    }
}
