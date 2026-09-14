<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\Mistral\Ocr\Result\Image;
use Symfony\AI\Platform\Bridge\Mistral\Ocr\Result\OcrResult;
use Symfony\AI\Platform\Bridge\Mistral\Ocr\Result\Page;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tac Tacelosky <tacman@gmail.com>
 */
final class OcrClient implements ApiClientInterface
{
    use HttpStatusErrorHandlingTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Ocr;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('The payload must be an array of normalized document content, but a string was given to "%s".', self::class));
        }

        return new RawHttpResult($this->httpClient->request('POST', 'https://api.mistral.ai/v1/ocr', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'document' => $payload,
            ]),
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): ObjectResult
    {
        $httpResponse = $result->getObject();

        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

        $data = $result->getData();

        if (!isset($data['pages']) || !\is_array($data['pages'])) {
            throw new RuntimeException('Response does not contain pages.');
        }

        $pages = array_map(static function (array $page): Page {
            $images = array_map(static fn (array $image): Image => new Image(
                $image['id'] ?? '',
                $image['top_left_x'] ?? null,
                $image['top_left_y'] ?? null,
                $image['bottom_right_x'] ?? null,
                $image['bottom_right_y'] ?? null,
                $image['image_base64'] ?? null,
            ), $page['images'] ?? []);

            return new Page(
                $page['index'] ?? 0,
                $page['markdown'] ?? '',
                $images,
                $page['dimensions'] ?? null,
            );
        }, $data['pages']);

        return new ObjectResult(new OcrResult(
            $pages,
            $data['model'] ?? '',
            $data['usage_info'] ?? null,
            $data['document_annotation'] ?? null,
        ));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
