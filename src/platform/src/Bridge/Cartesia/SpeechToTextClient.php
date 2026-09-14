<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cartesia;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SpeechToTextClient extends AbstractCartesiaClient
{
    public function supports(Model $model): bool
    {
        return $model->supports(Capability::SPEECH_TO_TEXT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return $this->post(
            path: '/stt',
            payload: [
                ...$options,
                'model' => $model->getName(),
                'file' => fopen($payload['input_audio']['path'], 'r'),
                'timestamp_granularities[]' => 'word',
            ],
            multipart: true,
        );
    }

    public function convert(RawResultInterface $result, array $options = []): TextResult
    {
        return new TextResult($result->getData()['text']);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
