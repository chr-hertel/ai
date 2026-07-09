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

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Image as ImageContent;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TransportInterface;

/**
 * OpenAI /v1/images/generations and /v1/images/edits contract handler (gpt-image-*).
 *
 * @see https://platform.openai.com/docs/api-reference/images/create
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ImageGenerationClient implements EndpointClientInterface
{
    public const ENDPOINT = 'openai.images_generations';

    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    /**
     * @param array{image?: ImageContent, ...} $options
     */
    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('The image prompt must be a string, but "%s" was given to "%s".', get_debug_type($payload), self::class));
        }

        // A source image turns the request into an edit (different endpoint and encoding).
        $envelope = isset($options['image'])
            ? $this->editEnvelope($model, $payload, $options)
            : new RequestEnvelope(
                payload: array_merge($options, [
                    'model' => $model->getName(),
                    'prompt' => $payload,
                ]),
                path: '/v1/images/generations',
            );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        $data = $raw->getData();

        if (!isset($data['data'][0])) {
            throw new RuntimeException('No image generated.');
        }

        // The images endpoint only returns base64-encoded images; PNG is the default output format.
        $mimeType = 'image/'.($options['output_format'] ?? 'png');

        $images = [];
        foreach ($data['data'] as $image) {
            $images[] = BinaryResult::fromBase64($image['b64_json'], $mimeType);
        }

        if (1 === \count($images)) {
            return $images[0];
        }

        return new MultiPartResult($images);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * @param array{image: ImageContent, ...} $options
     */
    private function editEnvelope(Model $model, string $prompt, array $options): RequestEnvelope
    {
        $image = $options['image'];
        unset($options['image']);

        $fields = array_merge($options, [
            'model' => $model->getName(),
            'prompt' => $prompt,
        ]);

        // The multipart body is built by hand: the HttpClient form encoder cannot advertise the image
        // MIME type without the (optional) symfony/mime component and would fall back to
        // "application/octet-stream", which the images endpoint rejects.
        $boundary = 'symfony-ai-'.bin2hex(random_bytes(16));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= \sprintf("--%s\r\nContent-Disposition: form-data; name=\"%s\"\r\n\r\n%s\r\n", $boundary, $name, $value);
        }

        $format = $image->getFormat();
        $filename = $image->getFilename() ?? 'image.'.(substr($format, strpos($format, '/') + 1) ?: 'png');
        $body .= \sprintf("--%s\r\nContent-Disposition: form-data; name=\"image\"; filename=\"%s\"\r\nContent-Type: %s\r\n\r\n%s\r\n", $boundary, $filename, $format, $image->asBinary());
        $body .= \sprintf("--%s--\r\n", $boundary);

        return new RequestEnvelope(
            payload: $fields,
            headers: ['Content-Type' => 'multipart/form-data; boundary='.$boundary],
            path: '/v1/images/edits',
            body: $body,
        );
    }
}
