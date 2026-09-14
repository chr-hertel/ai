<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway;

use Symfony\AI\Platform\Bridge\Generic\ChatCompletionsClient as GenericChatCompletionsClient;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChatCompletionsClient extends GenericChatCompletionsClient
{
    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return parent::convert($raw, $options);
        }

        $this->transport->throwOnError($raw, $options);

        $data = $raw->getData();

        if (isset($data['error']) && 'content_filter' !== ($data['error']['code'] ?? null)) {
            $errorMessage = $data['error']['message'] ?? '';

            if ('context_length_exceeded' === ($data['error']['code'] ?? null)
                || str_contains($errorMessage, 'context length')
                || str_contains($errorMessage, 'max_model_len')
            ) {
                throw new ExceedContextSizeException('' !== $errorMessage ? $errorMessage : 'Context size exceeded');
            }

            throw new RuntimeException(\sprintf('Error "%s": "%s".', $data['error']['type'] ?? $data['error']['code'] ?? 'unknown', $data['error']['message'] ?? 'Unknown error'));
        }

        return parent::convert($raw, $options);
    }
}
