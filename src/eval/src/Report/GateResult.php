<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Report;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class GateResult
{
    public function __construct(
        private readonly string $gate,
        private readonly string $variant,
        private readonly bool $passed,
        private readonly string $message,
    ) {
    }

    public function getGate(): string
    {
        return $this->gate;
    }

    public function getVariant(): string
    {
        return $this->variant;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
