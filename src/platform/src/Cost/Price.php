<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Cost;

/**
 * Prices of a model per one million tokens.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Price
{
    public function __construct(
        private readonly float $input,
        private readonly float $output,
        private readonly ?float $cacheRead = null,
        private readonly ?float $cacheWrite = null,
    ) {
    }

    public function getInput(): float
    {
        return $this->input;
    }

    public function getOutput(): float
    {
        return $this->output;
    }

    /**
     * Price of tokens read from the prompt cache, falls back to the input price.
     */
    public function getCacheRead(): float
    {
        return $this->cacheRead ?? $this->input;
    }

    /**
     * Price of tokens written into the prompt cache, falls back to the input price.
     */
    public function getCacheWrite(): float
    {
        return $this->cacheWrite ?? $this->input;
    }
}
