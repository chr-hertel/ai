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
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VideoClient extends AbstractMiniMaxClient
{
    /**
     * How long MiniMax may reasonably take, carried in the job handle so a caller does not have to
     * know that video generation runs an order of magnitude longer than speech synthesis.
     */
    private const MAX_DURATION = 600;

    private readonly MiniMaxJobClient $jobClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] string $apiKey,
        string $endpoint = 'https://api.minimax.io/v1',
        ?MiniMaxJobClient $jobClient = null,
    ) {
        parent::__construct($httpClient, $apiKey, $endpoint);

        $this->jobClient = $jobClient ?? new MiniMaxJobClient($httpClient, $apiKey, $endpoint);
    }

    public function supports(Model $model): bool
    {
        return $model->supports(Capability::TEXT_TO_VIDEO)
            || $model->supports(Capability::IMAGE_TO_VIDEO)
            || $model->supports(Capability::VIDEO_WITH_SUBJECT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $json = $options;
        $json['model'] = $model->getName();
        $json['prompt'] = $this->extractText($payload);

        return $this->post('video_generation', $json);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->guardHttpStatus($result);

        $data = $result->getData();

        $this->throwOnBusinessError($data);

        return $this->startJob($this->jobClient, $data, 'query/video_generation', 'video/mp4', self::MAX_DURATION);
    }
}
