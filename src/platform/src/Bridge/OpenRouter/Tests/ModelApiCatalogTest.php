<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\ModelApiCatalog;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelApiCatalogTest extends TestCase
{
    public function testGetModelLoadsFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'id' => 'anthropic/claude-3-opus',
                        'architecture' => [
                            'input_modalities' => ['text', 'image'],
                            'output_modalities' => ['text'],
                        ],
                    ],
                ],
            ]),
            new JsonMockResponse([
                'data' => [],
            ]),
        ]);

        $catalog = new ModelApiCatalog($httpClient);
        $model = $catalog->getModel('anthropic/claude-3-opus');

        $this->assertSame('anthropic/claude-3-opus', $model->getName());
        $this->assertTrue($model->accepts(Modality::TEXT));
        $this->assertTrue($model->accepts(Modality::IMAGE));
        $this->assertTrue($model->produces(Modality::TEXT));
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testGetModelsLoadsFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'id' => 'openai/gpt-4',
                        'architecture' => [
                            'input_modalities' => ['text'],
                            'output_modalities' => ['text'],
                        ],
                    ],
                    [
                        'id' => 'google/gemini-pro-vision',
                        'architecture' => [
                            'input_modalities' => ['text', 'image'],
                            'output_modalities' => ['text'],
                        ],
                    ],
                ],
            ]),
            new JsonMockResponse([
                'data' => [
                    [
                        'id' => 'openai/text-embedding-ada-002',
                    ],
                ],
            ]),
        ]);

        $catalog = new ModelApiCatalog($httpClient);
        $models = $catalog->getModels();

        // Should include base models (openrouter/auto, @preset) + API models + embeddings
        $this->assertArrayHasKey('openrouter/auto', $models);
        $this->assertArrayHasKey('openrouter/free', $models);
        $this->assertArrayHasKey('@preset', $models);
        $this->assertArrayHasKey('openai/gpt-4', $models);
        $this->assertArrayHasKey('google/gemini-pro-vision', $models);
        $this->assertArrayHasKey('openai/text-embedding-ada-002', $models);

        $this->assertSame(CompletionsModel::class, $models['openai/gpt-4']['class']);
        $this->assertSame(CompletionsModel::class, $models['google/gemini-pro-vision']['class']);
        $this->assertSame(EmbeddingsModel::class, $models['openai/text-embedding-ada-002']['class']);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testModelsAreOnlyLoadedOnce()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'id' => 'test/model',
                        'architecture' => [
                            'input_modalities' => ['text'],
                            'output_modalities' => ['text'],
                        ],
                    ],
                ],
            ]),
            new JsonMockResponse([
                'data' => [],
            ]),
        ]);

        $catalog = new ModelApiCatalog($httpClient);

        // Call getModels twice
        $catalog->getModels();
        $catalog->getModels();

        // Should only make 2 API calls total (models + embeddings), not 4
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testGetModelWithAudioInputModality()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'id' => 'openai/whisper',
                        'architecture' => [
                            'input_modalities' => ['audio'],
                            'output_modalities' => ['text'],
                        ],
                    ],
                ],
            ]),
            new JsonMockResponse([
                'data' => [],
            ]),
        ]);

        $catalog = new ModelApiCatalog($httpClient);
        $model = $catalog->getModel('openai/whisper');

        $this->assertTrue($model->accepts(Modality::AUDIO));
        $this->assertTrue($model->produces(Modality::TEXT));
    }

    public function testGetModelWithFileInputModality()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'id' => 'anthropic/claude-pdf',
                        'architecture' => [
                            'input_modalities' => ['text', 'file'],
                            'output_modalities' => ['text'],
                        ],
                    ],
                ],
            ]),
            new JsonMockResponse([
                'data' => [],
            ]),
        ]);

        $catalog = new ModelApiCatalog($httpClient);
        $model = $catalog->getModel('anthropic/claude-pdf');

        $this->assertTrue($model->accepts(Modality::TEXT));
        $this->assertTrue($model->accepts(Modality::PDF));
        $this->assertTrue($model->produces(Modality::TEXT));
    }

    public function testGetModelWithVideoInputModality()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'id' => 'google/gemini-video',
                        'architecture' => [
                            'input_modalities' => ['text', 'video'],
                            'output_modalities' => ['text'],
                        ],
                    ],
                ],
            ]),
            new JsonMockResponse([
                'data' => [],
            ]),
        ]);

        $catalog = new ModelApiCatalog($httpClient);
        $model = $catalog->getModel('google/gemini-video');

        $this->assertTrue($model->accepts(Modality::TEXT));
        $this->assertTrue($model->accepts(Modality::VIDEO));
        $this->assertTrue($model->produces(Modality::TEXT));
    }

    public function testGetModelWithImageOutputModality()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'id' => 'openai/dall-e',
                        'architecture' => [
                            'input_modalities' => ['text'],
                            'output_modalities' => ['image'],
                        ],
                    ],
                ],
            ]),
            new JsonMockResponse([
                'data' => [],
            ]),
        ]);

        $catalog = new ModelApiCatalog($httpClient);
        $model = $catalog->getModel('openai/dall-e');

        $this->assertTrue($model->accepts(Modality::TEXT));
        $this->assertTrue($model->produces(Modality::IMAGE));
    }

    public function testPresetModelStillWorksWithApiCatalog()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [],
            ]),
            new JsonMockResponse([
                'data' => [],
            ]),
        ]);

        $catalog = new ModelApiCatalog($httpClient);
        $model = $catalog->getModel('@preset/my-preset');

        $this->assertSame('@preset/my-preset', $model->getName());
        $this->assertEqualsCanonicalizing(Task::cases(), $model->getTasks());
        $this->assertEqualsCanonicalizing(Modality::cases(), $model->getInputModalities());
        $this->assertEqualsCanonicalizing(Modality::cases(), $model->getOutputModalities());
        $this->assertEqualsCanonicalizing(Feature::cases(), $model->getFeatures());
    }

    public function testAutoRouterModelStillWorksWithApiCatalog()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [],
            ]),
            new JsonMockResponse([
                'data' => [],
            ]),
        ]);

        $catalog = new ModelApiCatalog($httpClient);
        $model = $catalog->getModel('openrouter/auto');

        $this->assertSame('openrouter/auto', $model->getName());
        $this->assertEqualsCanonicalizing(Task::cases(), $model->getTasks());
        $this->assertEqualsCanonicalizing(Modality::cases(), $model->getInputModalities());
        $this->assertEqualsCanonicalizing(Modality::cases(), $model->getOutputModalities());
        $this->assertEqualsCanonicalizing(Feature::cases(), $model->getFeatures());
    }
}
