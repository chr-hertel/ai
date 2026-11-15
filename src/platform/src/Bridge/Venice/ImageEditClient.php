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
 * Routes Venice "image edit" / "upscale" / "background-remove" requests to the appropriate endpoint
 * depending on the option `mode` (`edit` | `upscale` | `background-remove`). Defaults to `edit`. The
 * image comes from a normalized `Image` or from a raw `image` string holding a base64 payload, a data
 * URL or an HTTP URL.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ImageEditClient extends AbstractVeniceClient
{
    use ImageResultConversionTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Venice && $model->supports(Capability::IMAGE_TO_IMAGE);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $payload = new VenicePayload($payload);

        $mode = \is_string($options['mode'] ?? null) ? $options['mode'] : 'edit';
        unset($options['mode']);

        return match ($mode) {
            'upscale' => $this->postJson('image/upscale', [
                ...$options,
                ...$payload->asUpscalePayload(),
                'model' => $model->getName(),
            ]),
            'background-remove' => $this->postJson('image/background-remove', [
                ...$options,
                'image' => $payload->asImageEditPayload()['image'],
            ]),
            default => $this->postJson('image/edit', [
                ...$options,
                ...$payload->asImageEditPayload($options, requirePrompt: true),
                'model' => $model->getName(),
            ]),
        };
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        return $this->convertImages($result);
    }
}
