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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MusicClient extends AbstractMiniMaxClient
{
    public function supports(Model $model): bool
    {
        return $model->supports(Capability::MUSIC);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!\array_key_exists('lyrics', $options)) {
            throw new InvalidArgumentException('The "lyrics" option is required when generating music.');
        }

        $json = $options;
        $json['model'] = $model->getName();
        $json['prompt'] = $this->extractText($payload);

        if (!\array_key_exists('output_format', $json)) {
            $json['output_format'] = 'hex';
        }

        return $this->post('music_generation', $json);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->guardHttpStatus($result);

        $data = $result->getData();

        $this->throwOnBusinessError($data);

        return new BinaryResult($this->decodeHexAudio($data), 'audio/mpeg');
    }
}
