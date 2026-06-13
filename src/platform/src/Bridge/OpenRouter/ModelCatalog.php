<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Task;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractOpenRouterModelCatalog
{
    /**
     * @param array<string, array{class: string, tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}> $additionalModels
     */
    public function __construct(
        array $additionalModels = [],
    ) {
        parent::__construct();

        // OpenRouter provides access to many different models from various providers
        // The model list is changed every few days. This list is generated at a static date.
        // This catalog only contains the current state of the model list as default models
        // For a full and up-2-date list of models incl. all capabilities, use the ModelApiCatalog

        // STATIC LIST START
        // This list is generated from external metadata. Run dev/update-model-catalogs.php to refresh it.
        $defaultModels = [
            'ai21/jamba-large-1.7' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'aion-labs/aion-1.0' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'aion-labs/aion-1.0-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'aion-labs/aion-2.0' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'aion-labs/aion-rp-llama-3.1-8b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'alfredpros/codellama-7b-instruct-solidity' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'allenai/olmo-3-32b-think' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'amazon/nova-2-lite-v1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'amazon/nova-lite-v1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'amazon/nova-micro-v1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'amazon/nova-premier-v1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'amazon/nova-pro-v1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'anthracite-org/magnum-v4-72b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'anthropic/claude-3-haiku' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'anthropic/claude-3.5-haiku' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'anthropic/claude-haiku-4.5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'anthropic/claude-opus-4' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'anthropic/claude-opus-4.1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'anthropic/claude-opus-4.5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'anthropic/claude-opus-4.6' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'anthropic/claude-opus-4.6-fast' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'anthropic/claude-opus-4.7' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'anthropic/claude-opus-4.7-fast' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'anthropic/claude-opus-4.8' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'anthropic/claude-opus-4.8-fast' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'anthropic/claude-sonnet-4' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'anthropic/claude-sonnet-4.5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'anthropic/claude-sonnet-4.6' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'arcee-ai/coder-large' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'arcee-ai/maestro-reasoning' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'arcee-ai/spotlight' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'arcee-ai/trinity-large-thinking' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'arcee-ai/trinity-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'arcee-ai/virtuoso-large' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'baai/bge-base-en-v1.5' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'baai/bge-large-en-v1.5' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'baai/bge-m3' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'baidu/ernie-4.5-21b-a3b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'baidu/ernie-4.5-21b-a3b-thinking' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'baidu/ernie-4.5-300b-a47b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'baidu/ernie-4.5-vl-28b-a3b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'baidu/ernie-4.5-vl-424b-a47b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'baidu/qianfan-ocr-fast' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'bytedance-seed/seed-1.6' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'bytedance-seed/seed-1.6-flash' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'bytedance-seed/seed-2.0-lite' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'bytedance-seed/seed-2.0-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'bytedance/ui-tars-1.5-7b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'cognitivecomputations/dolphin-mistral-24b-venice-edition:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'cohere/command-a' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'cohere/command-r-08-2024' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'cohere/command-r-plus-08-2024' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'cohere/command-r7b-12-2024' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepcogito/cogito-v2.1-671b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-chat' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-chat-v3-0324' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-chat-v3.1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-r1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-r1-0528' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-r1-distill-llama-70b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'deepseek/deepseek-r1-distill-qwen-32b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-v3.1-terminus' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-v3.2' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-v3.2-exp' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-v3.2-speciale' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-v4-flash' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'deepseek/deepseek-v4-flash:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'deepseek/deepseek-v4-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'essentialai/rnj-1-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-2.0-flash-001' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-2.0-flash-lite-001' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-2.5-flash' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-2.5-flash-image' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-2.5-flash-lite' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-2.5-flash-lite-preview-09-2025' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-2.5-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-2.5-pro-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-2.5-pro-preview-05-06' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-3-flash-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-3-pro-image-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-3.1-flash-image-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-3.1-flash-lite' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-3.1-flash-lite-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-3.1-pro-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-3.1-pro-preview-customtools' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-3.5-flash' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemini-embedding-001' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'google/gemini-embedding-2' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'google/gemini-embedding-2-preview' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'google/gemma-2-27b-it' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemma-3-12b-it' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemma-3-27b-it' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemma-3-4b-it' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemma-3n-e4b-it' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'google/gemma-4-26b-a4b-it' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemma-4-26b-a4b-it:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'google/gemma-4-31b-it' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'google/gemma-4-31b-it:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'google/lyria-3-clip-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::AUDIO,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'google/lyria-3-pro-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::AUDIO,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'gryphe/mythomax-l2-13b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'ibm-granite/granite-4.0-h-micro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'ibm-granite/granite-4.1-8b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'inception/mercury-2' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'inclusionai/ling-2.6-1t' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'inclusionai/ling-2.6-flash' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'inclusionai/ring-2.6-1t' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'inflection/inflection-3-pi' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'inflection/inflection-3-productivity' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'intfloat/e5-base-v2' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'intfloat/e5-large-v2' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'intfloat/multilingual-e5-large' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'kwaipilot/kat-coder-pro-v2' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'liquid/lfm-2-24b-a2b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'liquid/lfm-2.5-1.2b-instruct:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'liquid/lfm-2.5-1.2b-thinking:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'mancer/weaver' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'meta-llama/llama-3-70b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'meta-llama/llama-3-8b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'meta-llama/llama-3.1-70b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'meta-llama/llama-3.1-8b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'meta-llama/llama-3.2-11b-vision-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'meta-llama/llama-3.2-1b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'meta-llama/llama-3.2-3b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'meta-llama/llama-3.2-3b-instruct:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'meta-llama/llama-3.3-70b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'meta-llama/llama-3.3-70b-instruct:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'meta-llama/llama-4-maverick' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'meta-llama/llama-4-scout' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'meta-llama/llama-guard-3-8b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'meta-llama/llama-guard-4-12b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'microsoft/phi-4' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'microsoft/phi-4-mini-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'microsoft/wizardlm-2-8x22b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'minimax/minimax-01' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'minimax/minimax-m1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'minimax/minimax-m2' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'minimax/minimax-m2-her' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'minimax/minimax-m2.1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'minimax/minimax-m2.5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'minimax/minimax-m2.5:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'minimax/minimax-m2.7' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'minimax/minimax-m3' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'mistralai/codestral-2508' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/codestral-embed-2505' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'mistralai/devstral-2512' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/devstral-medium' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/devstral-small' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/ministral-14b-2512' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/ministral-3b-2512' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/ministral-8b-2512' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-7b-instruct-v0.1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'mistralai/mistral-embed-2312' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'mistralai/mistral-large' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-large-2407' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-large-2411' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-large-2512' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-medium-3' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-medium-3-5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-medium-3.1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-nemo' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-saba' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-small-24b-instruct-2501' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-small-2603' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mistral-small-3.1-24b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'mistralai/mistral-small-3.2-24b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/mixtral-8x22b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/pixtral-large-2411' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'mistralai/voxtral-small-24b-2507' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::AUDIO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'moonshotai/kimi-k2' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'moonshotai/kimi-k2-0905' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'moonshotai/kimi-k2-thinking' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'moonshotai/kimi-k2.5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'moonshotai/kimi-k2.6' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'moonshotai/kimi-k2.6:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'morph/morph-v3-fast' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'morph/morph-v3-large' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'nex-agi/deepseek-v3.1-nex-n1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'nousresearch/hermes-2-pro-llama-3-8b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-405b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-405b:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-70b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'nousresearch/hermes-4-405b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'nousresearch/hermes-4-70b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'nvidia/llama-3.3-nemotron-super-49b-v1.5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'nvidia/llama-nemotron-embed-vl-1b-v2:free' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'nvidia/nemotron-3-nano-30b-a3b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'nvidia/nemotron-3-nano-30b-a3b:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'nvidia/nemotron-3-super-120b-a12b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'nvidia/nemotron-3-super-120b-a12b:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'nvidia/nemotron-3-ultra-550b-a55b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'nvidia/nemotron-3-ultra-550b-a55b:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'nvidia/nemotron-3.5-content-safety:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'nvidia/nemotron-nano-12b-v2-vl:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'nvidia/nemotron-nano-9b-v2' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'nvidia/nemotron-nano-9b-v2:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-3.5-turbo' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-3.5-turbo-0613' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-3.5-turbo-16k' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-3.5-turbo-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4-0314' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4-1106-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4-turbo' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4-turbo-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4.1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4.1-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4.1-nano' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4o' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4o-2024-05-13' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4o-2024-08-06' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4o-2024-11-20' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4o-audio-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::AUDIO,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::AUDIO,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4o-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4o-mini-2024-07-18' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4o-mini-search-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-4o-search-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5-chat' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5-codex' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5-image' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5-image-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5-nano' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.1-chat' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.1-codex' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.1-codex-max' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.1-codex-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.2' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.2-chat' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.2-codex' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.2-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.3-chat' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.3-codex' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.4' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.4-image-2' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.4-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.4-nano' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.4-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-5.5-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-audio' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::AUDIO,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::AUDIO,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-audio-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::AUDIO,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::AUDIO,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-chat-latest' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-oss-120b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-oss-120b:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'openai/gpt-oss-20b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/gpt-oss-20b:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'openai/gpt-oss-safeguard-20b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'openai/o1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/o1-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/o3' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/o3-deep-research' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/o3-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/o3-mini-high' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/o3-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/o4-mini' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/o4-mini-deep-research' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/o4-mini-high' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openai/text-embedding-3-large' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'openai/text-embedding-3-small' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'openai/text-embedding-ada-002' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'openrouter/fusion' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'openrouter/owl-alpha' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'openrouter/pareto-code' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'perceptron/perceptron-mk1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'perplexity/pplx-embed-v1-0.6b' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'perplexity/pplx-embed-v1-4b' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'perplexity/sonar' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'perplexity/sonar-deep-research' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'perplexity/sonar-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'perplexity/sonar-pro-search' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'perplexity/sonar-reasoning-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'poolside/laguna-m.1:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'poolside/laguna-xs.2:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'prime-intellect/intellect-3' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen-2.5-72b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen-2.5-7b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'qwen/qwen-2.5-coder-32b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'qwen/qwen-plus' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'qwen/qwen-plus-2025-07-28' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen-plus-2025-07-28:thinking' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen2.5-vl-72b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-14b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-235b-a22b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'qwen/qwen3-235b-a22b-2507' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-235b-a22b-thinking-2507' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-30b-a3b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-30b-a3b-instruct-2507' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-30b-a3b-thinking-2507' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-32b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-8b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-coder' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-coder-30b-a3b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-coder-flash' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'qwen/qwen3-coder-next' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-coder-plus' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-coder:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'qwen/qwen3-embedding-4b' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'qwen/qwen3-embedding-8b' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'qwen/qwen3-max' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'qwen/qwen3-max-thinking' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-next-80b-a3b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-next-80b-a3b-instruct:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-next-80b-a3b-thinking' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-vl-235b-a22b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-vl-235b-a22b-thinking' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'qwen/qwen3-vl-30b-a3b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-vl-30b-a3b-thinking' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-vl-32b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'qwen/qwen3-vl-8b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3-vl-8b-thinking' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.5-122b-a10b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.5-27b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.5-35b-a3b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.5-397b-a17b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.5-9b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.5-flash-02-23' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.5-plus-02-15' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.5-plus-20260420' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.6-27b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.6-35b-a3b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.6-flash' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.6-max-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.6-plus' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.7-max' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'qwen/qwen3.7-plus' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'rekaai/reka-edge' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'rekaai/reka-flash-3' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'relace/relace-apply-3' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'relace/relace-search' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'sao10k/l3-euryale-70b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'sao10k/l3-lunaris-8b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'sao10k/l3.1-70b-hanami-x1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'sao10k/l3.1-euryale-70b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'sao10k/l3.3-euryale-70b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'sentence-transformers/all-minilm-l12-v2' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'sentence-transformers/all-minilm-l6-v2' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'sentence-transformers/all-mpnet-base-v2' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'sentence-transformers/multi-qa-mpnet-base-dot-v1' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'sentence-transformers/paraphrase-minilm-l6-v2' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'stepfun/step-3.5-flash' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'stepfun/step-3.7-flash' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'switchpoint/router' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'tencent/hunyuan-a13b-instruct' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'tencent/hy3-preview' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'thedrummer/cydonia-24b-v4.1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'thedrummer/rocinante-12b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'thedrummer/skyfall-36b-v2' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'thedrummer/unslopnemo-12b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'thenlper/gte-base' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'thenlper/gte-large' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'undi95/remm-slerp-l2-13b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'upstage/solar-pro-3' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'writer/palmyra-x5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'x-ai/grok-4.20' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'x-ai/grok-4.20-multi-agent' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'x-ai/grok-4.3' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'x-ai/grok-build-0.1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'xiaomi/mimo-v2-flash' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'xiaomi/mimo-v2-omni' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'xiaomi/mimo-v2-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'xiaomi/mimo-v2.5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'xiaomi/mimo-v2.5-pro' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'z-ai/glm-4-32b' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'z-ai/glm-4.5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'z-ai/glm-4.5-air' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'z-ai/glm-4.5-air:free' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'z-ai/glm-4.5v' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'z-ai/glm-4.6' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'z-ai/glm-4.6v' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'z-ai/glm-4.7' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'z-ai/glm-4.7-flash' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'z-ai/glm-5' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'z-ai/glm-5-turbo' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'z-ai/glm-5.1' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'z-ai/glm-5v-turbo' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            '~anthropic/claude-haiku-latest' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            '~anthropic/claude-opus-latest' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            '~anthropic/claude-sonnet-latest' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            '~google/gemini-flash-latest' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            '~google/gemini-pro-latest' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            '~moonshotai/kimi-latest' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            '~openai/gpt-latest' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            '~openai/gpt-mini-latest' => [
                'class' => CompletionsModel::class,
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
        ];
        // STATIC LIST END

        $this->models = [
            ...$this->models,
            ...$defaultModels,
            ...$additionalModels,
        ];
    }

    public function getModel(string $modelName): Model
    {
        if ('' === $modelName) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }

        $parsed = $this->parseModelName($modelName);
        $actualModelName = $parsed['name'];
        $catalogKey = $parsed['catalogKey'];
        $options = $parsed['options'];

        if (!isset($this->models[$catalogKey])) {
            // Add model to the list as default model with a permissive profile
            $this->models[$catalogKey] = [
                'class' => CompletionsModel::class,
                'tasks' => Task::cases(),
                'input' => Modality::cases(),
                'output' => Modality::cases(),
                'features' => Feature::cases(),
            ];
        }

        $modelConfig = $this->models[$catalogKey];
        $modelClass = $modelConfig['class'];

        if (!class_exists($modelClass)) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" does not exist.', $modelClass));
        }

        $features = $modelConfig['features'] ?? [];
        if (CompletionsModel::class === $modelClass && !\in_array(Feature::STREAMING, $features, true)) {
            // Streaming is allowed for any model: https://openrouter.ai/docs/api/reference/streaming
            $features[] = Feature::STREAMING;
        }

        $model = new $modelClass(
            $actualModelName,
            $modelConfig['tasks'] ?? [],
            $modelConfig['input'] ?? [],
            $modelConfig['output'] ?? [],
            $features,
            $options,
        );
        if (!$model instanceof Model) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" must extend "%s".', $modelClass, CompletionsModel::class));
        }

        return $model;
    }
}
