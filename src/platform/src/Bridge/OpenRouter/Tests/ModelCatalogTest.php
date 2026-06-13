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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\ModelCatalog;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'openrouter/auto' => [
            'openrouter/auto',
            CompletionsModel::class,
            Task::cases(), Modality::cases(), Modality::cases(), Feature::cases(),
        ];

        yield 'anthropic/claude-sonnet-4.5' => [
            'anthropic/claude-sonnet-4.5',
            CompletionsModel::class,
            [], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT],
        ];

        yield 'google/gemini-2.5-flash structured' => [
            'google/gemini-2.5-flash',
            CompletionsModel::class,
            [], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::VIDEO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT],
        ];

        yield 'openai/gpt-4.1 structured' => [
            'openai/gpt-4.1',
            CompletionsModel::class,
            [], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT],
        ];

        yield 'mistralai/mistral-large-2411 structured' => [
            'mistralai/mistral-large-2411',
            CompletionsModel::class,
            [], [Modality::TEXT, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT],
        ];

        yield 'openai/gpt-5' => [
            'openai/gpt-5',
            CompletionsModel::class,
            [], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT],
        ];

        yield 'openai/gpt-5-mini' => [
            'openai/gpt-5-mini',
            CompletionsModel::class,
            [], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT],
        ];

        yield 'google/gemini-2.5-flash-image' => [
            'google/gemini-2.5-flash-image',
            CompletionsModel::class,
            [], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT, Modality::IMAGE], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT],
        ];

        yield 'openai/text-embedding-3-large' => [
            'openai/text-embedding-3-large',
            EmbeddingsModel::class,
            [Task::EMBEDDING], [Modality::TEXT], [], [],
        ];
    }

    public function testGetModelThrowsExceptionForEmptyModelName()
    {
        $catalog = new ModelCatalog();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model name cannot be empty.');

        // @phpstan-ignore argument.type (testing invalid input)
        $catalog->getModel('');
    }

    public function testGetModelReturnsDefaultModelForUnknownModel()
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('unknown/model');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertSame('unknown/model', $model->getName());
        $this->assertEqualsCanonicalizing([], $model->getTasks());
        $this->assertEqualsCanonicalizing([], $model->getInputModalities());
        $this->assertEqualsCanonicalizing([], $model->getOutputModalities());
        $this->assertEqualsCanonicalizing([Feature::STREAMING], $model->getFeatures());
    }

    public function testGetModelWithPreset()
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('@preset/my-custom-preset');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertSame('@preset/my-custom-preset', $model->getName());
        $this->assertEqualsCanonicalizing(Task::cases(), $model->getTasks());
        $this->assertEqualsCanonicalizing(Modality::cases(), $model->getInputModalities());
        $this->assertEqualsCanonicalizing(Modality::cases(), $model->getOutputModalities());
        $this->assertEqualsCanonicalizing(Feature::cases(), $model->getFeatures());
    }

    public function testGetModelWithModifier()
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('deepseek/deepseek-v3.1-terminus:exacto');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertSame('deepseek/deepseek-v3.1-terminus:exacto', $model->getName());
        $this->assertEqualsCanonicalizing([], $model->getTasks());
        $this->assertEqualsCanonicalizing([Modality::TEXT], $model->getInputModalities());
        $this->assertEqualsCanonicalizing([Modality::TEXT], $model->getOutputModalities());
        $this->assertEqualsCanonicalizing([Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT], $model->getFeatures());
    }

    /**
     * @param array<string, array{class: string, tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}> $additionalModels
     */
    #[DataProvider('additionalModelsProvider')]
    public function testConstructorWithAdditionalModels(array $additionalModels, string $modelName, string $expectedClass)
    {
        $catalog = new ModelCatalog($additionalModels);

        $model = $catalog->getModel($modelName);

        $this->assertInstanceOf($expectedClass, $model);
    }

    /**
     * @return iterable<string, array{array<string, array{class: string, tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}>, string, string}>
     */
    public static function additionalModelsProvider(): iterable
    {
        yield 'custom completions model' => [
            [
                'custom/my-model' => [
                    'class' => CompletionsModel::class,
                    'input' => [
                        Modality::TEXT,
                    ],
                    'output' => [
                        Modality::TEXT,
                    ],
                ],
            ],
            'custom/my-model',
            CompletionsModel::class,
        ];

        yield 'custom embeddings model' => [
            [
                'custom/my-embedding' => [
                    'class' => EmbeddingsModel::class,
                    'tasks' => [
                        Task::EMBEDDING,
                    ],
                    'input' => [
                        Modality::TEXT,
                    ],
                ],
            ],
            'custom/my-embedding',
            EmbeddingsModel::class,
        ];
    }

    public function testGetModelThrowsExceptionForUnknownModel()
    {
        // Override parent test because OpenRouter catalog does NOT throw exception
        // for unknown models - it creates a default model instead
        $this->markTestSkipped('OpenRouter ModelCatalog creates default models for unknown model names.');
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
