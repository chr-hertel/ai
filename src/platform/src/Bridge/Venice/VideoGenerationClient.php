<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Venice queues a video generation and hands out a queue id, so this client polls `video/retrieve`
 * until the job leaves the `PROCESSING` state or the attempts run out.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class VideoGenerationClient extends AbstractVeniceClient
{
    public function __construct(
        HttpClientInterface $httpClient,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
        parent::__construct($httpClient);
    }

    public function supports(Model $model): bool
    {
        if (!$model instanceof Venice) {
            return false;
        }

        return $model->supports(Capability::IMAGE_TO_VIDEO)
            || $model->supports(Capability::TEXT_TO_VIDEO)
            || $model->supports(Capability::VIDEO_TO_VIDEO);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $maxAttempts = \is_int($options['max_polling_attempts'] ?? null) ? $options['max_polling_attempts'] : 120;
        $pollingInterval = \is_int($options['polling_interval_seconds'] ?? null) ? $options['polling_interval_seconds'] : 1;

        unset($options['max_polling_attempts'], $options['polling_interval_seconds']);

        $finalPayload = (new VenicePayload($payload))->asVideoGenerationPayload($model, $options);

        $queueData = $this->httpClient->request('POST', 'video/queue', [
            'json' => [
                ...$finalPayload,
                'model' => $model->getName(),
            ],
        ])->toArray();

        $retrieveBody = [
            'model' => $queueData['model'],
            'queue_id' => $queueData['queue_id'],
        ];

        for ($attempt = 0; $attempt < $maxAttempts; ++$attempt) {
            $response = $this->httpClient->request('POST', 'video/retrieve', [
                'json' => $retrieveBody,
            ]);

            $contentType = $response->getHeaders(false)['content-type'][0] ?? '';

            if (!str_contains($contentType, 'application/json')) {
                return new RawHttpResult($response);
            }

            $data = $response->toArray(false);

            if ('PROCESSING' !== ($data['status'] ?? '')) {
                return new RawHttpResult($response);
            }

            $this->clock->sleep($pollingInterval);
        }

        throw new RuntimeException(\sprintf('Video generation timed out after %d polling attempts.', $maxAttempts));
    }

    public function convert(RawResultInterface $result, array $options = []): BinaryResult
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        return new BinaryResult($response->getContent());
    }
}
