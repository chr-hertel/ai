<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\ModelCatalog;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelCatalogTest extends TestCase
{
    public function testModelCatalogStillReturnsTwoModelsWhenApiReturnsEmpty()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/models', $url);

            return new JsonMockResponse([]);
        }, 'https://api.elevenlabs.io/v1/');

        $models = (new ModelCatalog($httpClient))->getModels();

        $this->assertCount(2, $models);
        $this->assertArrayHasKey('scribe_v1', $models);
        $this->assertArrayHasKey('scribe_v2', $models);
    }

    public function testModelCatalogCannotReturnModelFromApiWhenUndefined()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/models', $url);

            return new JsonMockResponse([
                [
                    'model_id' => 'bar',
                    'name' => 'bar',
                    'can_do_text_to_speech' => true,
                    'can_do_voice_conversion' => false,
                ],
            ]);
        }, 'https://api.elevenlabs.io/v1/');

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "foo" cannot be retrieved from the API.');
        $this->expectExceptionCode(0);
        $modelCatalog->getModel('foo');
    }

    public function testModelCatalogCannotReturnUnsupportedModelFromApi()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/models', $url);

            return new JsonMockResponse([
                [
                    'model_id' => 'foo',
                    'name' => 'foo',
                    'can_do_text_to_speech' => false,
                    'can_do_voice_conversion' => false,
                ],
            ]);
        }, 'https://api.elevenlabs.io/v1/');

        $modelCatalog = new ModelCatalog($httpClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "foo" is not supported, please check the ElevenLabs API.');
        $this->expectExceptionCode(0);
        $modelCatalog->getModel('foo');
    }

    public function testModelCatalogCanReturnSpecificTtsModelFromApi()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/models', $url);

            return new JsonMockResponse([
                [
                    'model_id' => 'foo',
                    'name' => 'foo',
                    'can_do_text_to_speech' => true,
                    'can_do_voice_conversion' => false,
                ],
                [
                    'model_id' => 'bar',
                    'name' => 'bar',
                    'can_do_text_to_speech' => false,
                    'can_do_voice_conversion' => true,
                ],
            ]);
        }, 'https://api.elevenlabs.io/v1/');

        $modelCatalog = new ModelCatalog($httpClient);

        $model = $modelCatalog->getModel('foo');

        $this->assertSame('foo', $model->getName());
        $this->assertEqualsCanonicalizing([Task::SPEECH_SYNTHESIS], $model->getTasks());
        $this->assertEqualsCanonicalizing([Modality::TEXT], $model->getInputModalities());
        $this->assertEqualsCanonicalizing([Modality::AUDIO], $model->getOutputModalities());
        $this->assertEqualsCanonicalizing([], $model->getFeatures());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnSpecificSttModelFromApi()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/models', $url);

            return new JsonMockResponse([
                [
                    'model_id' => 'foo',
                    'name' => 'foo',
                    'can_do_text_to_speech' => false,
                    'can_do_voice_conversion' => true,
                ],
            ]);
        }, 'https://api.elevenlabs.io/v1/');

        $modelCatalog = new ModelCatalog($httpClient);

        $model = $modelCatalog->getModel('foo');

        $this->assertSame('foo', $model->getName());
        $this->assertEqualsCanonicalizing([Task::TRANSCRIPTION], $model->getTasks());
        $this->assertEqualsCanonicalizing([Modality::AUDIO], $model->getInputModalities());
        $this->assertEqualsCanonicalizing([Modality::TEXT], $model->getOutputModalities());
        $this->assertEqualsCanonicalizing([], $model->getFeatures());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnModelsFromApi()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/models', $url);

            return new JsonMockResponse([
                [
                    'model_id' => 'foo',
                    'name' => 'foo',
                    'can_do_text_to_speech' => false,
                    'can_do_voice_conversion' => true,
                ],
                [
                    'model_id' => 'bar',
                    'name' => 'bar',
                    'can_do_text_to_speech' => true,
                    'can_do_voice_conversion' => false,
                ],
            ]);
        }, 'https://api.elevenlabs.io/v1/');

        $modelCatalog = new ModelCatalog($httpClient);

        $models = $modelCatalog->getModels();

        $this->assertCount(4, $models);
        $this->assertArrayHasKey('foo', $models);
        $this->assertArrayHasKey('bar', $models);
        $this->assertArrayHasKey('scribe_v1', $models);
        $this->assertArrayHasKey('scribe_v2', $models);
        $this->assertSame(ElevenLabs::class, $models['foo']['class']);
        $this->assertCount(3, $models['foo']['capabilities']);
        $this->assertEquals([Task::TRANSCRIPTION], [Modality::AUDIO], [Modality::TEXT], [], $models['foo']['capabilities']);
        $this->assertSame(ElevenLabs::class, $models['bar']['class']);
        $this->assertCount(3, $models['bar']['capabilities']);
        $this->assertEquals([Task::SPEECH_SYNTHESIS], [Modality::TEXT], [Modality::AUDIO], [], $models['bar']['capabilities']);
    }
}
