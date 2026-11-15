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
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TextToSpeechClient extends AbstractTogetherClient
{
    use PayloadTextTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Together && $model->supports(Capability::TEXT_TO_SPEECH);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!isset($options['voice'])) {
            throw new InvalidArgumentException('The "voice" option is required for text-to-speech.');
        }

        return $this->postJson('/v1/audio/speech', array_merge($options, [
            'model' => $model->getName(),
            'input' => $this->extractText($payload, 'input'),
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): BinaryResult
    {
        $this->throwOnError($result);

        if (!$result instanceof RawHttpResult) {
            throw new RuntimeException(\sprintf('"%s" requires an HTTP-backed raw result, got "%s".', self::class, $result::class));
        }

        $mimeType = match ($options['response_format'] ?? 'wav') {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            default => 'application/octet-stream',
        };

        return new BinaryResult($result->getObject()->getContent(), $mimeType);
    }
}
