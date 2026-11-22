<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval;

/**
 * One configuration of the agent under test, e.g. a model and a prompt version.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Variant
{
    /**
     * @param array<string, mixed> $parameters passed to the agent factory of the suite, e.g. "model" or "prompt_version"
     */
    public function __construct(
        private readonly string $name,
        private readonly array $parameters = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function get(string $parameter, mixed $default = null): mixed
    {
        return $this->parameters[$parameter] ?? $default;
    }
}
