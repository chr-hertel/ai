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
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * MiniMax `/video_generation` client.
 *
 * Video generation is always asynchronous: the endpoint returns a task id that is
 * polled until the rendered file is available.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VideoClient extends AbstractMiniMaxClient
{
    use AsyncTaskTrait;

    public const ENDPOINT = 'minimax.video_generation';

    /**
     * Maximum number of polls before giving up on a video task; video generation is
     * considerably slower than audio and routinely runs for several minutes (~10 minutes).
     */
    private const MAX_VIDEO_POLLS = 600;

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
        $json = $options;
        $json['model'] = $model->getName();
        $json['prompt'] = $this->extractText($payload);

        return $this->post('video_generation', $json);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->guardHttpStatus($result);

        return $this->handleAsyncTask($result->getData(), 'query/video_generation', 'video/mp4', self::MAX_VIDEO_POLLS);
    }
}
