<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class GenerateClient extends AbstractDecartClient
{
    public function supports(Model $model): bool
    {
        return $model->supports(Capability::TEXT_TO_IMAGE)
            || $model->supports(Capability::TEXT_TO_VIDEO);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return $this->post(
            path: \sprintf('/generate/%s', $model->getName()),
            payload: [
                'prompt' => \is_string($payload) ? $payload : $payload['text'],
                ...$options,
            ],
        );
    }

    public function convert(RawResultInterface $result, array $options = []): BinaryResult
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        $headers = $response->getHeaders();

        return new BinaryResult($response->getContent(), $headers['content-type'][0]);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
