<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * The image and speech endpoints both take a single piece of text, either as the payload itself or
 * under their own key.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
trait PayloadTextTrait
{
    /**
     * @param array<string|int, mixed>|string $payload
     */
    protected function extractText(array|string $payload, string $arrayKey): string
    {
        if (\is_string($payload)) {
            return $payload;
        }

        if (isset($payload[$arrayKey]) && \is_string($payload[$arrayKey])) {
            return $payload[$arrayKey];
        }

        if (isset($payload['text']) && \is_string($payload['text'])) {
            return $payload['text'];
        }

        throw new InvalidArgumentException(\sprintf('The payload must be a string or contain a "%s" key.', $arrayKey));
    }
}
