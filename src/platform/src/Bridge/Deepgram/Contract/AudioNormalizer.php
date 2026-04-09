<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Contract;

use Symfony\AI\Platform\Bridge\Deepgram\Deepgram;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type AudioData array{type: 'input_audio', input_audio: array{
 *     data: string,
 *     path: string|null,
 *     format: string,
 * }}
 */
final class AudioNormalizer extends ModelContractNormalizer
{
    /**
     * @param Audio $data
     *
     * @return AudioData
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'type' => 'input_audio',
            'input_audio' => [
                'data' => $data->asBase64(),
                'path' => $data->asPath(),
                'format' => match ($data->getFormat()) {
                    'audio/mpeg' => 'mp3',
                    'audio/wav' => 'wav',
                    default => $data->getFormat(),
                },
            ],
        ];
    }

    protected function supportedDataClass(): string
    {
        return Audio::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Deepgram;
    }
}
