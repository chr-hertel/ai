<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AmazeeAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\AmazeeAi\ModelApiCatalog;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelApiCatalogTest extends TestCase
{
    public function testLazyLoadModels()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $models = $catalog->getModels();

        $this->assertArrayHasKey('claude-3-5-sonnet', $models);
        $this->assertArrayHasKey('titan-embed-text-v2:0', $models);
    }

    public function testCompletionsModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $model = $catalog->getModel('claude-3-5-sonnet');

        $this->assertInstanceOf(CompletionsModel::class, $model);
    }

    public function testEmbeddingsModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $model = $catalog->getModel('titan-embed-text-v2:0');

        $this->assertInstanceOf(EmbeddingsModel::class, $model);
    }

    public function testCompletionsCapabilities()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $models = $catalog->getModels();
        $model = $models['claude-3-5-sonnet'];

        $this->assertContains(Task::TEXT_GENERATION, $model['tasks']);
        $this->assertContains(Modality::TEXT, $model['output']);
        $this->assertContains(Feature::STREAMING, $model['features']);
        $this->assertContains(Modality::IMAGE, $model['input']);
        $this->assertContains(Feature::TOOL_CALLING, $model['features']);
        $this->assertContains(Feature::STRUCTURED_OUTPUT, $model['features']);
    }

    public function testEmbeddingCapabilities()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $models = $catalog->getModels();
        $model = $models['titan-embed-text-v2:0'];

        $this->assertContains(Task::EMBEDDING, $model['tasks']);
        $this->assertContains(Modality::TEXT, $model['input']);
        $this->assertContains(Feature::MULTIPLE_INPUTS, $model['features']);
    }

    public function testModelNotFound()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $this->expectException(ModelNotFoundException::class);
        $catalog->getModel('non-existent-model');
    }

    public function testModelsAreLoadedOnlyOnce()
    {
        $callCount = 0;
        $httpClient = new MockHttpClient(function () use (&$callCount) {
            ++$callCount;

            return new JsonMockResponse($this->getModelInfoResponse());
        });

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com', 'test-key');

        $catalog->getModels();
        $catalog->getModels();
        $catalog->getModel('claude-3-5-sonnet');

        $this->assertSame(1, $callCount);
    }

    public function testWithoutApiKey()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($this->getModelInfoResponse()),
        ]);

        $catalog = new ModelApiCatalog($httpClient, 'https://litellm.example.com');

        $models = $catalog->getModels();

        $this->assertNotEmpty($models);
    }

    /**
     * @return array<string, mixed>
     */
    private function getModelInfoResponse(): array
    {
        return [
            'data' => [
                [
                    'model_name' => 'claude-3-5-sonnet',
                    'model_info' => [
                        'mode' => 'chat',
                        'supports_image_input' => true,
                        'supports_audio_input' => false,
                        'supports_tool_calling' => true,
                        'supports_response_schema' => true,
                    ],
                ],
                [
                    'model_name' => 'claude-3-5-haiku',
                    'model_info' => [
                        'mode' => 'chat',
                        'supports_image_input' => false,
                        'supports_tool_calling' => true,
                        'supports_response_schema' => false,
                    ],
                ],
                [
                    'model_name' => 'titan-embed-text-v2:0',
                    'model_info' => [
                        'mode' => 'embedding',
                        'supports_multiple_inputs' => true,
                    ],
                ],
            ],
        ];
    }
}
