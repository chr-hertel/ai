<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\TransportInterface;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Result\Segment;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Result\Transcript;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Task;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TranscriptionClient implements ApiClientInterface
{
    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $modelClass = Whisper::class,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof $this->modelClass;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $task = $options['task'] ?? Task::TRANSCRIPTION;
        $endpoint = Task::TRANSCRIPTION === $task ? 'transcriptions' : 'translations';
        unset($options['task']);

        if ($options['verbose'] ?? false) {
            $options['response_format'] = 'verbose_json';
            unset($options['verbose']);
        }

        return $this->transport->send(\sprintf('/v1/audio/%s', $endpoint), array_merge($options, $payload, ['model' => $model->getName()]), ['Content-Type' => 'multipart/form-data']);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->transport->throwOnError($result, $options);

        $data = $result->getData();

        if (isset($data['error']['code']) && 'content_filter' === $data['error']['code']) {
            throw new ContentFilterException($data['error']['message']);
        }

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error "%s"-%s (%s): "%s".', $data['error']['code'] ?? '-', $data['error']['type'] ?? '-', $data['error']['param'] ?? '-', $data['error']['message'] ?? '-'));
        }

        if (!($options['verbose'] ?? false) && !isset($data['text'])) {
            throw new RuntimeException(\sprintf('The response is missing the required "text" field. Response data: "%s"', json_encode($data)));
        }
        $result = ($options['verbose'] ?? false) ? $this->getVerboseResult($data) : new TextResult($data['text']);

        if (isset($data['usage'])) {
            $result->getMetadata()->add('usage', $data['usage']);
        }

        return $result;
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * @param array{
     *     text: string,
     *     language: string,
     *     duration: float,
     *     segments: array{start: float, end: float, text: string}[]
     * } $data
     */
    private function getVerboseResult(array $data): ObjectResult
    {
        if (!isset($data['text']) || !isset($data['language']) || !isset($data['duration']) || !isset($data['segments'])) {
            throw new RuntimeException('The verbose response is missing required fields: text, language, duration, or segments.');
        }

        return new ObjectResult(new Transcript(
            $data['text'],
            $data['language'],
            $data['duration'],
            array_map(static fn (array $segment) => new Segment($segment['start'], $segment['end'], $segment['text']), $data['segments']),
        ));
    }
}
