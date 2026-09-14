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
use Symfony\AI\Platform\Bridge\OpenAi\Batch\BatchClient;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\JobClient;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\TransportInterface;
use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter as OpenResponsesResultConverter;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResponsesClient implements ApiClientInterface
{
    public const BATCH = 'batch';

    /**
     * The endpoint this client talks to, and therefore the one its batches are submitted to.
     */
    public const PATH = '/v1/responses';

    private readonly OpenResponsesResultConverter $resultConverter;

    /**
     * @param class-string<Model> $modelClass
     * @param BatchClient|null    $batchClient submits the invocations with the "batch" option, which are refused without it
     * @param string              $provider    the name stamped onto the handles of the batches this client starts
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $modelClass = Gpt::class,
        private readonly ?BatchClient $batchClient = null,
        private readonly string $provider = 'openai',
    ) {
        $this->resultConverter = new OpenResponsesResultConverter();
    }

    public function supports(Model $model): bool
    {
        return $model instanceof $this->modelClass;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        // OpenAI performs automatic prompt caching; no explicit cache_control
        // annotation is needed and cacheRetention is not an OpenAI concept.
        // Strip it so it is never forwarded to the Responses API.
        unset($options['cacheRetention']);

        if ($options[self::BATCH] ?? false) {
            unset($options[self::BATCH]);

            return $this->submitBatch($model, $payload, $options);
        }

        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        return $this->transport->send(self::PATH, $this->createBody($model, $payload, $options));
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        $this->transport->throwOnError($raw, $options);

        // A batched invocation is answered with the batch OpenAI created, not with what it will produce.
        if ($options[self::BATCH] ?? false) {
            return $this->startBatch($raw);
        }

        return $this->resultConverter->convert($raw, $options);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     *
     * @return array<string, mixed>
     */
    private function createBody(Model $model, array $payload, array $options): array
    {
        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $schema = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema'];
            $options['text']['format'] = $schema;
            $options['text']['format']['name'] = $schema['name'];
            $options['text']['format']['type'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['type'];

            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        return array_merge($options, ['model' => $model->getName()], $payload);
    }

    /**
     * Turns the normalized inputs into one request body each, keyed by the identifier to report it back under.
     *
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     */
    private function submitBatch(Model $model, array|string $payload, array $options): RawResultInterface
    {
        if (null === $this->batchClient) {
            throw new InvalidArgumentException('This deployment of the Responses API does not support batches.');
        }

        if (!\is_array($payload) || [] === $payload) {
            throw new InvalidArgumentException(\sprintf('A batch invocation expects a non-empty array of inputs, "%s" given.', get_debug_type($payload)));
        }

        if ($options['stream'] ?? false) {
            throw new InvalidArgumentException('A batch is answered as a file hours later, so it cannot be streamed.');
        }

        $requests = [];

        foreach ($payload as $customId => $input) {
            // A single input normalizes into the keys of one Responses request, not into a map of them.
            if (\in_array($customId, ['input', 'instructions'], true)) {
                throw new InvalidArgumentException('A batch invocation expects an array of inputs, keyed by the identifier to report each result under, and not a single input.');
            }

            if (!\is_array($input) || !\is_array($input['input'] ?? null)) {
                throw new InvalidArgumentException(\sprintf('The input "%s" of the batch did not normalize into a request, a batch takes the same inputs as any other invocation - a "%s", for instance - one per identifier.', $customId, MessageBag::class));
            }

            $requests[$customId] = $this->createBody($model, $input, $options);
        }

        return $this->batchClient->submit($requests);
    }

    /**
     * OpenAI accepted the batch, so the invocation produces a reference to it rather than a result;
     * resolving it is the job of {@see JobClient}.
     */
    private function startBatch(RawResultInterface $result): JobResult
    {
        $data = $result->getData();
        $id = $data['id'] ?? null;

        if (!\is_string($id) || '' === $id) {
            throw new RuntimeException('The OpenAI response does not contain a batch identifier.');
        }

        $endpoint = $data['endpoint'] ?? null;

        return new JobResult(new JobHandle($id, [
            'kind' => JobClient::KIND,
            'endpoint' => \is_string($endpoint) ? $endpoint : self::PATH,
        ], $this->provider, self::windowToSeconds($data['completion_window'] ?? null), JobClient::DEFAULT_POLL_INTERVAL));
    }

    /**
     * The completion window is the longest the batch may take, carried on the handle so nobody has to guess.
     */
    private static function windowToSeconds(mixed $window): int
    {
        if (\is_string($window) && 1 === preg_match('/^(\d+)h$/', $window, $matches) && 0 < (int) $matches[1]) {
            return (int) $matches[1] * 3600;
        }

        return JobClient::DEFAULT_MAX_DURATION;
    }
}
