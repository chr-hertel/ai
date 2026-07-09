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

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * MiniMax text-to-speech client (`/t2a_v2`, or `/t2a_async_v2` with the `async` option).
 *
 * The asynchronous variant returns a task id that is polled until the audio file is ready.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SpeechClient extends AbstractMiniMaxClient
{
    use AsyncTaskTrait;

    public const ENDPOINT = 'minimax.text_to_speech';

    /**
     * Maximum number of polls before giving up on an asynchronous audio task (~2 minutes).
     */
    private const MAX_AUDIO_POLLS = 120;

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

        if ($options['async'] ?? false) {
            return $this->handleAsyncTask($data, 'query/t2a_async_query_v2', 'audio/mpeg', self::MAX_AUDIO_POLLS);
        }

        return new BinaryResult($this->decodeHexAudio($data), 'audio/mpeg');
    }
}
