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

use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Context\PromptReference;

/**
 * One version of a named prompt template.
 *
 * Placeholders use single curly braces, e.g. "You are the assistant of {shop_name}."
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Prompt
{
    private readonly PromptReference $reference;

    public function __construct(
        string $name,
        string $version,
        private readonly string $template,
    ) {
        $this->reference = new PromptReference($name, $version, substr(hash('sha256', $template), 0, 12));
    }

    public function getReference(): PromptReference
    {
        return $this->reference;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @param array<string, string|\Stringable> $variables
     */
    public function render(array $variables = []): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = (string) $value;
        }

        return strtr($this->template, $replacements);
    }

    /**
     * @param array<string, string|\Stringable> $variables
     */
    public function toInstruction(array $variables = []): Instruction
    {
        return new Instruction($this->render($variables), $this->reference);
    }
}
