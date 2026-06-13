<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Task;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog implements ModelCatalogInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getModel(string $modelName): Ollama
    {
        $response = $this->httpClient->request('POST', 'api/show', [
            'json' => [
                'model' => $modelName,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Cannot connect to the Ollama API: "%s".', $e->getMessage()), previous: $e);
        }

        if (200 !== $statusCode) {
            $errorMessage = $this->extractErrorMessage($response);

            if (404 === $statusCode) {
                throw new ModelNotFoundException(null !== $errorMessage ? \sprintf('Model "%s" not found: "%s".', $modelName, $errorMessage) : \sprintf('Model "%s" not found.', $modelName));
            }

            throw new RuntimeException(null !== $errorMessage ? \sprintf('Cannot load model information from the Ollama API (Status code: %d): "%s".', $statusCode, $errorMessage) : \sprintf('Cannot load model information from the Ollama API (Status code: %d).', $statusCode));
        }

        $payload = $response->toArray();

        if ([] === $payload['capabilities']) {
            throw new InvalidArgumentException('The model information could not be retrieved from the Ollama API. Your Ollama server might be too old. Try upgrade it.');
        }

        $tasks = [];
        $input = [];
        $output = [];
        $features = [];

        foreach ($payload['capabilities'] as $capability) {
            switch ($capability) {
                case 'embedding':
                    $tasks[] = Task::EMBEDDING;
                    break;
                case 'completion':
                    $tasks[] = Task::TEXT_GENERATION;
                    $input[] = Modality::TEXT;
                    $output[] = Modality::TEXT;
                    break;
                case 'tools':
                    $features[] = Feature::TOOL_CALLING;
                    break;
                case 'thinking':
                    $features[] = Feature::THINKING;
                    break;
                case 'vision':
                    $input[] = Modality::IMAGE;
                    break;
                case 'audio':
                    $input[] = Modality::AUDIO;
                    break;
                case 'insert':
                    $features[] = Feature::FILL_IN_THE_MIDDLE;
                    break;
                default:
                    throw new InvalidArgumentException(\sprintf('The "%s" capability is not supported', $capability));
            }
        }

        if (!\in_array(Task::EMBEDDING, $tasks, true)) {
            $features[] = Feature::STRUCTURED_OUTPUT;
        }

        return new Ollama($modelName, $tasks, $input, $output, $features);
    }

    public function getModels(): array
    {
        $response = $this->httpClient->request('GET', 'api/tags');

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Cannot connect to the Ollama API: "%s".', $e->getMessage()), previous: $e);
        }

        if (200 !== $statusCode) {
            $errorMessage = $this->extractErrorMessage($response);

            throw new RuntimeException(null !== $errorMessage ? \sprintf('Cannot retrieve models from the Ollama API (Status code: %d): "%s".', $statusCode, $errorMessage) : \sprintf('Cannot retrieve models from the Ollama API (Status code: %d).', $statusCode));
        }

        $models = $response->toArray();

        if ([] === $models['models']) {
            return [];
        }

        return array_merge(...array_map(
            function (array $model): array {
                $retrievedModel = $this->getModel($model['name']);

                return [
                    $retrievedModel->getName() => [
                        'class' => Ollama::class,
                        'tasks' => $retrievedModel->getTasks(),
                        'input' => $retrievedModel->getInputModalities(),
                        'output' => $retrievedModel->getOutputModalities(),
                        'features' => $retrievedModel->getFeatures(),
                    ],
                ];
            },
            $models['models'],
        ));
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface) {
            return null;
        }

        if ('' === $content) {
            return null;
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

            if (\is_array($decoded) && isset($decoded['error'])) {
                return $decoded['error'];
            }
        } catch (\JsonException) {
            // not JSON, fall through to return raw content
        }

        return $content;
    }
}
