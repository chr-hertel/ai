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
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ImageGenerationClient extends AbstractVeniceClient
{
    use ImageResultConversionTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Venice && $model->supports(Capability::TEXT_TO_IMAGE);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return $this->postJson('image/generate', [
            ...$options,
            'prompt' => (new VenicePayload($payload))->asImageGeneration(),
            'model' => $model->getName(),
        ]);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        return $this->convertImages($result);
    }
}
