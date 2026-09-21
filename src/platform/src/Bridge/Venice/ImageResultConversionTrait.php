<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Venice answers every image endpoint the same way: raw bytes, or a JSON body carrying one or more
 * base64 images.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
trait ImageResultConversionTrait
{
    protected function convertImages(RawResultInterface $result): ResultInterface
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';

        if (!str_contains($contentType, 'application/json')) {
            return new BinaryResult($response->getContent());
        }

        $payload = $response->toArray();
        $images = \is_array($payload['images'] ?? null) ? $payload['images'] : [];

        if ([] === $images) {
            throw new InvalidArgumentException('No images found in the response.');
        }

        if (1 < \count($images)) {
            return new ChoiceResult(array_map(
                static function (mixed $imageAsBase64): BinaryResult {
                    if (!\is_string($imageAsBase64)) {
                        throw new InvalidArgumentException('Expected image data to be a base64 string.');
                    }

                    return new BinaryResult(base64_decode($imageAsBase64));
                },
                array_values($images),
            ));
        }

        if (!\is_string($images[0])) {
            throw new InvalidArgumentException('Expected image data to be a base64 string.');
        }

        return new BinaryResult(base64_decode($images[0]));
    }
}
