<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * MiniMax `/image_generation` client.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ImageClient extends AbstractMiniMaxClient
{
    public const ENDPOINT = 'minimax.image_generation';

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $json = $options;
        $json['model'] = $model->getName();
        $json['prompt'] = $this->extractText($payload);

        if (!\array_key_exists('response_format', $json)) {
            $json['response_format'] = 'base64';
        }

        return $this->post('image_generation', $json);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->guardHttpStatus($result);

        $data = $result->getData();

        if ([] !== ($data['data']['image_base64'] ?? [])) {
            $results = array_map(
                static fn (string $image): BinaryResult => new BinaryResult(base64_decode($image), 'image/jpeg'),
                $data['data']['image_base64'],
            );
        } else {
            $results = array_map(
                static fn (string $url): TextResult => new TextResult($url),
                $data['data']['image_urls'] ?? [],
            );
        }

        if ([] === $results) {
            throw new RuntimeException('The MiniMax response does not contain any image.');
        }

        if (1 === \count($results)) {
            return $results[0];
        }

        return new ChoiceResult(array_values($results));
    }
}
