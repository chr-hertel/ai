<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context;

/**
 * Identifies the prompt an instruction was built from, so traces, feedback and eval reports can tell prompt versions apart.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PromptReference implements \Stringable
{
    public function __construct(
        private readonly string $name,
        private readonly string $version,
        private readonly ?string $hash = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function __toString(): string
    {
        return $this->name.'@'.$this->version;
    }
}
