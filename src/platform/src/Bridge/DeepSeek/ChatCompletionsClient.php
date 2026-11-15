<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DeepSeek;

use Symfony\AI\Platform\Bridge\Generic\ChatCompletionsClient as GenericChatCompletionsClient;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\InvalidRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
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

        if (isset($data['error']['code'])) {
            match ($data['error']['code']) {
                'content_filter' => throw new ContentFilterException($data['error']['message']),
                'invalid_request_error' => throw new InvalidRequestException($data['error']['message']),
                default => throw new RuntimeException($data['error']['message']),
            };
        }

        return parent::convert($raw, $options);
    }
}
