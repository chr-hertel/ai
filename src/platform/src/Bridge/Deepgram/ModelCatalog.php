<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves Deepgram models from the live `/v1/models` endpoint.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog implements ModelCatalogInterface
{
    /**
     * @var array<string, array{class: class-string<Deepgram>, capabilities: list<Capability>}>|null
     */
    private ?array $models = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getModel(string $modelName): Deepgram
    {
        if ('' === $modelName) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }

        $models = $this->getModels();

        if (!isset($models[$modelName])) {
            throw new ModelNotFoundException(\sprintf('Model "%s" does not exist.', $modelName));
        }

        return new Deepgram($modelName, $models[$modelName]['capabilities']);
    }

    public function getModels(): array
    {
        if (null !== $this->models) {
            return $this->models;
        }

        try {
            $payload = $this->httpClient->request('GET', 'models')->toArray();
        } catch (HttpExceptionInterface $exception) {
            throw new RuntimeException(\sprintf('Deepgram returned status "%d" while listing models.', $exception->getResponse()->getStatusCode()), 0, $exception);
        } catch (DecodingExceptionInterface $exception) {
            throw new RuntimeException('Deepgram returned a malformed JSON payload while listing models.', 0, $exception);
        } catch (TransportExceptionInterface $exception) {
            throw new RuntimeException('Could not reach the Deepgram API to fetch the model catalog.', 0, $exception);
        }

        $capabilities = [
            'tts' => [Capability::INPUT_TEXT, Capability::TEXT_TO_SPEECH, Capability::OUTPUT_AUDIO],
            'stt' => [Capability::INPUT_AUDIO, Capability::SPEECH_TO_TEXT, Capability::OUTPUT_TEXT],
        ];

        $models = [];
        foreach ($capabilities as $type => $typeCapabilities) {
            $entries = $payload[$type] ?? [];
            if (!\is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!\is_array($entry)) {
                    continue;
                }

                $identifiers = [$entry['name'] ?? null, $entry['canonical_name'] ?? null];
                if ('stt' === $type) {
                    // /v1/listen accepts the architecture (e.g. "nova-3") as a model alias,
                    // while /v1/speak requires a full voice name
                    $identifiers[] = $entry['architecture'] ?? null;
                }

                foreach ($identifiers as $name) {
                    if (\is_string($name) && '' !== $name) {
                        $models[$name] = ['class' => Deepgram::class, 'capabilities' => $typeCapabilities];
                    }
                }
            }
        }

        return $this->models = $models;
    }
}
