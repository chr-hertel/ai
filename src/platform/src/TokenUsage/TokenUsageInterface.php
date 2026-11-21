<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\TokenUsage;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface TokenUsageInterface
{
    /**
     * All input tokens, including the ones read from or written into the prompt cache.
     */
    public function getPromptTokens(): ?int;

    /**
     * All generated tokens, including the thinking tokens.
     */
    public function getCompletionTokens(): ?int;

    /**
     * The part of the completion tokens the model spent on thinking.
     */
    public function getThinkingTokens(): ?int;

    public function getToolTokens(): ?int;

    /**
     * The prompt tokens the provider reports as cached, see the read and creation getters for what is billed how.
     */
    public function getCachedTokens(): ?int;

    /**
     * The part of the prompt tokens written into the prompt cache on this request.
     */
    public function getCacheCreationTokens(): ?int;

    /**
     * The part of the prompt tokens served from the prompt cache on this request.
     */
    public function getCacheReadTokens(): ?int;

    public function getRemainingTokens(): ?int;

    public function getRemainingTokensMinute(): ?int;

    public function getRemainingTokensMonth(): ?int;

    public function getTotalTokens(): ?int;

    /**
     * The model that consumed these tokens, as the provider reports it in its response - which is
     * usually the resolved snapshot ("gpt-4o-2024-08-06") rather than the alias that was requested.
     *
     * Null when the provider does not name a model in the payload the usage was read from.
     */
    public function getModel(): ?string;
}
