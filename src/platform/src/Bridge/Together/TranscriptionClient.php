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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TranscriptionClient extends AbstractTogetherClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Together && $model->supports(Capability::SPEECH_TO_TEXT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        // Together accepts a binary upload (resource) or a remote URL (string) as the "file".
        if (!\is_array($payload) || !isset($payload['file']) || (!\is_resource($payload['file']) && !\is_string($payload['file']))) {
            throw new InvalidArgumentException('The speech-to-text payload must contain a "file" key holding an audio resource or URL.');
        }

        $task = $options['task'] ?? 'transcription';
        unset($options['task']);

        $endpoint = 'translation' === $task ? '/v1/audio/translations' : '/v1/audio/transcriptions';

        return new RawHttpResult($this->httpClient->request('POST', $endpoint, [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'body' => array_merge($options, [
                'model' => $model->getName(),
                'file' => $payload['file'],
            ]),
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): TextResult
    {
        $this->throwOnError($result);

        $data = $result->getData();

        $this->throwOnApiError($data);

        if (!isset($data['text']) || !\is_string($data['text'])) {
            throw new RuntimeException('Response does not contain a transcription.');
        }

        return new TextResult($data['text']);
    }
}
