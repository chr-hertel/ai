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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextToSpeechClient extends AbstractCartesiaClient
{
    public function supports(Model $model): bool
    {
        return $model->supports(Capability::TEXT_TO_SPEECH);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $text = \is_string($payload) ? $payload : ($payload['text'] ?? throw new RuntimeException('The payload must contain a "text" key.'));

        return $this->post(
            path: '/tts/bytes',
            payload: [
                ...$options,
                'model_id' => $model->getName(),
                'transcript' => $text,
                'voice' => [
                    'mode' => 'id',
                    'id' => $options['voice'],
                ],
                'output_format' => $options['output_format'],
            ],
        );
    }

    public function convert(RawResultInterface $result, array $options = []): BinaryResult
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        return new BinaryResult($response->getContent(), 'audio/mpeg');
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
