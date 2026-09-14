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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechToTextClient extends AbstractVeniceClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Venice && $model->supports(Capability::SPEECH_TO_TEXT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'audio/transcriptions', [
            'body' => [
                'response_format' => 'json',
                ...$options,
                'file' => fopen((new VenicePayload($payload))->asSpeechToTextPayload(), 'r'),
                'model' => $model->getName(),
            ],
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): TextResult
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        $transcription = $response->toArray();

        if (!\is_string($transcription['text'] ?? null)) {
            throw new InvalidArgumentException('No transcription text found in the response.');
        }

        return new TextResult($transcription['text']);
    }
}
