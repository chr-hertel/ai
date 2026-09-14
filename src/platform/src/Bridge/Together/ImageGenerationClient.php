<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ImageGenerationClient extends AbstractTogetherClient
{
    use PayloadTextTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Together && $model->supports(Capability::OUTPUT_IMAGE);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return $this->postJson('/v1/images/generations', array_merge(['response_format' => 'base64'], $options, [
            'model' => $model->getName(),
            'prompt' => $this->extractText($payload, 'prompt'),
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): BinaryResult
    {
        $this->throwOnError($result);

        $data = $result->getData();

        $this->throwOnApiError($data);

        if (!isset($data['data']) || !\is_array($data['data']) || !isset($data['data'][0]) || !\is_array($data['data'][0])) {
            throw new RuntimeException('Response does not contain generated image data.');
        }

        $image = $data['data'][0];

        if (!isset($image['b64_json']) || !\is_string($image['b64_json'])) {
            throw new RuntimeException('Response does not contain base64-encoded image data, use the "base64" response format.');
        }

        // The Together API defaults the image "output_format" to jpeg.
        $mimeType = 'png' === ($options['output_format'] ?? 'jpeg') ? 'image/png' : 'image/jpeg';

        return BinaryResult::fromBase64($image['b64_json'], $mimeType);
    }
}
