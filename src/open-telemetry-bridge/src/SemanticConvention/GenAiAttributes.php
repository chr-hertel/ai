<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\SemanticConvention;

/**
 * Single mapping surface for the OpenTelemetry GenAI semantic conventions.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class GenAiAttributes
{
    // Last core release before gen_ai moved to semantic-conventions-genai, which has not released yet
    public const SCHEMA_URL = 'https://opentelemetry.io/schemas/1.41.0';

    public const OPERATION_NAME = 'gen_ai.operation.name';
    public const PROVIDER_NAME = 'gen_ai.provider.name';
    public const CONVERSATION_ID = 'gen_ai.conversation.id';

    public const REQUEST_MODEL = 'gen_ai.request.model';
    public const REQUEST_TEMPERATURE = 'gen_ai.request.temperature';
    public const REQUEST_TOP_P = 'gen_ai.request.top_p';
    public const REQUEST_MAX_TOKENS = 'gen_ai.request.max_tokens';
    public const REQUEST_STOP_SEQUENCES = 'gen_ai.request.stop_sequences';
    public const REQUEST_FREQUENCY_PENALTY = 'gen_ai.request.frequency_penalty';
    public const REQUEST_PRESENCE_PENALTY = 'gen_ai.request.presence_penalty';
    public const REQUEST_SEED = 'gen_ai.request.seed';

    public const RESPONSE_MODEL = 'gen_ai.response.model';
    public const RESPONSE_FINISH_REASONS = 'gen_ai.response.finish_reasons';

    public const USAGE_INPUT_TOKENS = 'gen_ai.usage.input_tokens';
    public const USAGE_OUTPUT_TOKENS = 'gen_ai.usage.output_tokens';
    public const USAGE_CACHE_READ_INPUT_TOKENS = 'gen_ai.usage.cache_read.input_tokens';
    public const USAGE_CACHE_CREATION_INPUT_TOKENS = 'gen_ai.usage.cache_creation.input_tokens';

    public const AGENT_NAME = 'gen_ai.agent.name';

    public const TOOL_NAME = 'gen_ai.tool.name';
    public const TOOL_CALL_ID = 'gen_ai.tool.call.id';
    public const TOOL_TYPE = 'gen_ai.tool.type';
    public const TOOL_CALL_ARGUMENTS = 'gen_ai.tool.call.arguments';
    public const TOOL_CALL_RESULT = 'gen_ai.tool.call.result';

    public const SYSTEM_INSTRUCTIONS = 'gen_ai.system_instructions';
    public const INPUT_MESSAGES = 'gen_ai.input.messages';
    public const OUTPUT_MESSAGES = 'gen_ai.output.messages';

    public const DATA_SOURCE_ID = 'gen_ai.data_source.id';

    public const ERROR_TYPE = 'error.type';

    public const OPERATION_CHAT = 'chat';
    public const OPERATION_EMBEDDINGS = 'embeddings';
    public const OPERATION_GENERATE_CONTENT = 'generate_content';
    public const OPERATION_INVOKE_AGENT = 'invoke_agent';
    public const OPERATION_EXECUTE_TOOL = 'execute_tool';
    public const OPERATION_RETRIEVAL = 'retrieval';

    /**
     * Option keys of the provider bridges mapped onto request attributes, first match wins.
     *
     * @var array<string, list<string>>
     */
    public const REQUEST_OPTIONS = [
        self::REQUEST_TEMPERATURE => ['temperature'],
        self::REQUEST_TOP_P => ['top_p'],
        self::REQUEST_MAX_TOKENS => ['max_tokens', 'max_output_tokens', 'max_completion_tokens'],
        self::REQUEST_STOP_SEQUENCES => ['stop', 'stop_sequences'],
        self::REQUEST_FREQUENCY_PENALTY => ['frequency_penalty'],
        self::REQUEST_PRESENCE_PENALTY => ['presence_penalty'],
        self::REQUEST_SEED => ['seed'],
    ];

    /**
     * Low-cardinality span name in the "{operation} {target}" form.
     *
     * @param non-empty-string $operation
     *
     * @return non-empty-string
     */
    public static function spanName(string $operation, ?string $target): string
    {
        if (null === $target || '' === $target) {
            return $operation;
        }

        return $operation.' '.$target;
    }
}
