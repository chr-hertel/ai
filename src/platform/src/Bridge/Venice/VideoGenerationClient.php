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
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Queues a video generation and answers with a handle; resolving it is the job of {@see VeniceJobClient}.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class VideoGenerationClient extends AbstractVeniceClient
{
    /**
     * @param string $provider the name stamped onto the handles of the generations this client queues
     */
    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $provider = 'venice',
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
        return $this->postJson('video/queue', [
            ...(new VenicePayload($payload))->asVideoGenerationPayload($model, $options),
            'model' => $model->getName(),
        ]);
    }

    public function convert(RawResultInterface $result, array $options = []): JobResult
    {
        $payload = $this->getCheckedResponse($result)->toArray();

        $queueId = $payload['queue_id'] ?? null;
        $model = $payload['model'] ?? null;

        if (!\is_string($queueId)) {
            throw new RuntimeException('The Venice response does not contain a queue identifier.');
        }

        if (!\is_string($model)) {
            throw new RuntimeException('The Venice response does not contain the model the generation was queued for.');
        }

        return new JobResult(new JobHandle(
            $queueId,
            ['queue_model' => $model],
            $this->provider,
            VeniceJobClient::DEFAULT_MAX_DURATION,
            VeniceJobClient::DEFAULT_POLL_INTERVAL,
        ));
    }
}
