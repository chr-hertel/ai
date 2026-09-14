<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras;

use Symfony\AI\Platform\Bridge\Generic\ChatCompletionsClient as GenericChatCompletionsClient;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
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

        if (isset($data['type'], $data['message']) && str_ends_with($data['type'], 'error')) {
            throw new RuntimeException(\sprintf('Cerebras API error: "%s"', $data['message']));
        }

        if (!isset($data['error']) && !isset($data['choices'][0])) {
            throw new RuntimeException('Response does not contain output.');
        }

        return parent::convert($raw, $options);
    }
}
