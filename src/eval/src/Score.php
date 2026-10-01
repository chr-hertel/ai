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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Score
{
    /**
     * @param float                      $value    normalized between 0.0 and 1.0
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        private readonly string $evaluator,
        private readonly float $value,
        private readonly bool $passed,
        private readonly ?string $explanation = null,
        private readonly array $metadata = [],
    ) {
    }

    public static function pass(string $evaluator, ?string $explanation = null): self
    {
        return new self($evaluator, 1.0, true, $explanation);
    }

    public static function fail(string $evaluator, string $explanation): self
    {
        return new self($evaluator, 0.0, false, $explanation);
    }

    public function getEvaluator(): string
    {
        return $this->evaluator;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
