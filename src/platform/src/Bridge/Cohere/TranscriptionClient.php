<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TranscriptionClient extends AbstractCohereClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof SpeechToText;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $body = array_merge($options, $payload, ['model' => $model->getName()]);

        return $this->post('/v2/audio/transcriptions', $body, true);
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): TextResult
    {
        $this->throwOnError($result);

        $data = $result->getData();

        if (!isset($data['text'])) {
            throw new RuntimeException('Response does not contain transcription text.');
        }

        return new TextResult($data['text']);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
