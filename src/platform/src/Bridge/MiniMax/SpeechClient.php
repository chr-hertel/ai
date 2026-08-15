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
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SpeechClient extends AbstractMiniMaxClient
{
    public function supports(Model $model): bool
    {
        return $model->supports(Capability::TEXT_TO_SPEECH);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $text = $this->extractText($payload);
        $async = (bool) ($options['async'] ?? false);
        unset($options['async']);

        $json = $options;
        $json['model'] = $model->getName();
        $json['text'] = $text;

        if (!$async && !\array_key_exists('output_format', $json)) {
            $json['output_format'] = 'hex';
        }

        return $this->post($async ? 't2a_async_v2' : 't2a_v2', $json);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->guardHttpStatus($result);

        $data = $result->getData();

        $this->throwOnBusinessError($data);

        if ($options['async'] ?? false) {
            // Unlike the synchronous endpoint, the asynchronous one delivers a tar bundling the audio
            // with a `.titles` and an `.extra` file, so the job client has to unpack the mp3 to make
            // both endpoints produce the same thing.
            return $this->startJob($data, 'query/t2a_async_query_v2', 'audio/mpeg', 'mp3');
        }

        return new BinaryResult($this->decodeHexAudio($data), 'audio/mpeg');
    }
}
