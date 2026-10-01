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

use Symfony\AI\Platform\Message\Content\File;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A context item carrying the instruction (system prompt) of an agent.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Instruction
{
    public function __construct(
        private readonly string|\Stringable|TranslatableInterface|File $content,
        private readonly ?PromptReference $reference = null,
    ) {
    }

    /**
     * The prompt this instruction was built from, if it comes from a versioned prompt.
     */
    public function getReference(): ?PromptReference
    {
        return $this->reference;
    }

    public function getContent(): string|\Stringable|TranslatableInterface|File
    {
        return $this->content;
    }
}
