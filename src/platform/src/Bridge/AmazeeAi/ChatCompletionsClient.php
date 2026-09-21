<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AmazeeAi;

use Symfony\AI\Platform\Bridge\Generic\ChatCompletionsClient as GenericChatCompletionsClient;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Chat Completions client for amazee.ai's LiteLLM proxy.
 *
 * LiteLLM may return finish_reason "tool_calls" for structured output responses but place the
 * content in message.content instead of message.tool_calls. That quirk is handled by the generic
 * client this class extends, which keys the tool-call branch on the payload rather than on the
 * finish reason and falls back to message.content.
 */
class ChatCompletionsClient extends GenericChatCompletionsClient
{
    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
