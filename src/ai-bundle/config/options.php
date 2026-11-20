<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Configurator;

use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelOptionsParser;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ExprBuilder;
use Symfony\Component\Config\Definition\Dumper\JsonSchemaDumper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Translation\TranslatorInterface;

return static function (DefinitionConfigurator $configurator): void {
    $import = fn (string $resource): ArrayNodeDefinition => require __DIR__.'/'.$resource.'.php';
    $assertUniqueNames = static fn (string $message, array $subTypes = []): \Closure => static function (array $types) use ($message, $subTypes): array {
        $namesByArgument = [];
        foreach ($types as $type => $services) {
            if (isset($subTypes[$type])) {
                $services = $services[$subTypes[$type]] ?? [];
                $type .= '.'.$subTypes[$type];
            }

            foreach (array_keys($services) as $name) {
                $namesByArgument[(new Target((string) $name))->getParsedName()][] = $type.'.'.$name;
            }
        }

        foreach ($namesByArgument as $names) {
            if (\count($names) > 1) {
                throw new InvalidArgumentException(\sprintf($message, implode('", "', $names)));
            }
        }

        return $types;
    };

    $tools = (new ArrayNodeDefinition('tools'))
        ->info('Tools are opt-in: set to true to inject all services tagged with "ai.tool", or configure an explicit list of tools. When the option is omitted (or set to null or false), no tools are registered.')
        ->addDefaultsIfNotSet()
        ->treatFalseLike(['enabled' => false])
        ->treatTrueLike(['enabled' => true])
        ->treatNullLike(['enabled' => false])
        ->beforeNormalization()
            ->ifArray()
            ->then(static function (array $v): array {
                $services = $v['services'] ?? $v;
                unset($services['enabled'], $services['execution_strategy']);
                $executionStrategy = $v['execution_strategy'] ?? null;

                return [
                    'enabled' => $v['enabled'] ?? ([] !== $services || null !== $executionStrategy),
                    'execution_strategy' => $executionStrategy,
                    'services' => $services,
                ];
            })
        ->end()
        ->children()
            ->booleanNode('enabled')->defaultFalse()->end()
            ->scalarNode('execution_strategy')
                ->info('The tool execution strategy. Built-in options are "sequential" (default) and "fiber". A custom service ID implementing ToolExecutorInterface can also be provided.')
                ->defaultNull()
            ->end()
            ->arrayNode('services')
                ->arrayPrototype()
                    ->children()
                        ->stringNode('service')->cannotBeEmpty()->end()
                        ->stringNode('agent')->cannotBeEmpty()->end()
                        ->stringNode('mcp_server')
                            ->info('A remote MCP server whose tools are exposed to this agent, as "<client>.<server>" referencing a connection configured under "mcp.clients".')
                            ->example('research.filesystem')
                            ->cannotBeEmpty()
                        ->end()
                        ->stringNode('name')->end()
                        ->stringNode('description')->end()
                        ->stringNode('method')->end()
                        ->stringNode('prefix')
                            ->info('Only with "mcp_server": prefix put in front of every remote tool name. Defaults to "<server>_".')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static function (string $v) {
                            return ['service' => $v];
                        })
                    ->end()
                    ->validate()
                        ->ifTrue(static function ($v) {
                            $configured = 0;
                            foreach (['service', 'agent', 'mcp_server'] as $key) {
                                if (isset($v[$key]) && '' !== $v[$key]) {
                                    ++$configured;
                                }
                            }

                            return 1 !== $configured;
                        })
                        ->thenInvalid('Exactly one of "service", "agent" or "mcp_server" must be configured.')
                    ->end()
                    ->validate()
                        ->ifTrue(static function ($v) {
                            if (!isset($v['prefix'])) {
                                return false;
                            }

                            return !isset($v['mcp_server']);
                        })
                        ->thenInvalid('The "prefix" option is only supported together with "mcp_server".')
                    ->end()
                    ->validate()
                        ->ifTrue(static function ($v) {
                            if (!isset($v['mcp_server'])) {
                                return false;
                            }

                            return !str_contains($v['mcp_server'], '.');
                        })
                        ->thenInvalid('Invalid MCP server reference, expected the "<client>.<server>" format.')
                    ->end()
                ->end()
            ->end()
        ->end();

    // the JSON schema of the configuration, added in Symfony 8.2, describes a list of tools only with this
    if (class_exists(JsonSchemaDumper::class)) {
        $tools->acceptAndWrap([ExprBuilder::TYPE_ARRAY], 'services');
    }

    $configurator->rootNode()
        ->children()
            ->arrayNode('platform')
                ->children()
                    ->append($import('platform/albert'))
                    ->append($import('platform/amazeeai'))
                    ->append($import('platform/anthropic'))
                    ->append($import('platform/azure'))
                    ->append($import('platform/bedrock'))
                    ->append($import('platform/cache'))
                    ->append($import('platform/cartesia'))
                    ->append($import('platform/cerebras'))
                    ->append($import('platform/cohere'))
                    ->append($import('platform/decart'))
                    ->append($import('platform/deepgram'))
                    ->append($import('platform/deepseek'))
                    ->append($import('platform/dockermodelrunner'))
                    ->append($import('platform/edenai'))
                    ->append($import('platform/elevenlabs'))
                    ->append($import('platform/failover'))
                    ->append($import('platform/fireworks'))
                    ->append($import('platform/gemini'))
                    ->append($import('platform/generic'))
                    ->append($import('platform/higgsfield'))
                    ->append($import('platform/huggingface'))
                    ->append($import('platform/lmstudio'))
                    ->append($import('platform/minimax'))
                    ->append($import('platform/mistral'))
                    ->append($import('platform/ollama'))
                    ->append($import('platform/openai'))
                    ->append($import('platform/openresponses'))
                    ->append($import('platform/openrouter'))
                    ->append($import('platform/ovh'))
                    ->append($import('platform/perplexity'))
                    ->append($import('platform/scaleway'))
                    ->append($import('platform/together'))
                    ->append($import('platform/transformersphp'))
                    ->append($import('platform/typesafe'))
                    ->append($import('platform/venice'))
                    ->append($import('platform/vertexai'))
                    ->append($import('platform/voyage'))
                ->end()
            ->end()
            ->arrayNode('agent')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->stringNode('platform')
                            ->info('Service name of platform')
                            ->defaultValue(PlatformInterface::class)
                        ->end()
                        ->variableNode('model')
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return !\is_string($v) && (!\is_array($v) || !isset($v['name']));
                                })
                                ->thenInvalid('Model must be a string or an array with a "name" key.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    // Check if both query parameters and options array are provided
                                    if (\is_array($v) && isset($v['name']) && isset($v['options']) && [] !== $v['options']) {
                                        return str_contains($v['name'], '?');
                                    }

                                    return false;
                                })
                                ->thenInvalid('Cannot use both query parameters in model name and options array.')
                            ->end()
                            ->beforeNormalization()
                                ->always(static function ($v) {
                                    if (\is_string($v)) {
                                        return $v;
                                    }

                                    // It's an array with 'name' and optionally 'options'
                                    $model = $v['name'];
                                    $options = $v['options'] ?? [];

                                    // Parse query parameters from model name if present
                                    if (str_contains($model, '?')) {
                                        $parsed = parse_url($model);
                                        $model = $parsed['path'] ?? '';

                                        if ('' === $model) {
                                            throw new InvalidConfigurationException('Model name cannot be empty.');
                                        }

                                        if (isset($parsed['scheme'])) {
                                            $model = $parsed['scheme'].':'.$model;
                                        }

                                        if (isset($parsed['query'])) {
                                            // If options array is also provided, throw an error
                                            if ([] !== $options) {
                                                throw new InvalidConfigurationException('Cannot use both query parameters in model name and options array.');
                                            }
                                            $options = ModelOptionsParser::parse($parsed['query']);
                                        }
                                    }

                                    // Return model string with options as query parameters
                                    if ([] === $options) {
                                        return $model;
                                    }

                                    array_walk_recursive($options, static function (mixed &$value): void {
                                        if (\is_bool($value)) {
                                            $value = $value ? 'true' : 'false';
                                        }
                                    });

                                    return $model.'?'.str_replace('%', '%%', http_build_query($options));
                                })
                            ->end()
                        ->end()
                        ->variableNode('memory')
                            ->info('Memory configuration: string for static memory, or array with "service" key for service reference')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_string($v) && '' === $v;
                                })
                                ->thenInvalid('Memory cannot be empty.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && !isset($v['service']);
                                })
                                ->thenInvalid('Memory array configuration must contain a "service" key.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && isset($v['service']) && '' === $v['service'];
                                })
                                ->thenInvalid('Memory service cannot be empty.')
                            ->end()
                        ->end()
                        ->arrayNode('prompt')
                            ->info('The system prompt configuration')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static function (string $v) {
                                    return ['text' => $v];
                                })
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    if (!\is_array($v)) {
                                        return false;
                                    }
                                    $hasTextOrFile = isset($v['text']) || isset($v['file']) || isset($v['registry']);

                                    return !$hasTextOrFile;
                                })
                                ->thenInvalid('Either "text", "file" or "registry" must be configured for prompt.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && \count(array_filter([$v['text'] ?? null, $v['file'] ?? null, $v['registry'] ?? null], static fn ($value): bool => null !== $value)) > 1;
                                })
                                ->thenInvalid('Cannot combine "text", "file" and "registry" for prompt. Choose one.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && !isset($v['registry']) && (isset($v['version']) || [] !== ($v['variables'] ?? []));
                                })
                                ->thenInvalid('The prompt "version" and "variables" are only used with "registry".')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && isset($v['registry']) && (($v['include_tools'] ?? false) || ($v['enable_translation'] ?? false));
                                })
                                ->thenInvalid('A prompt of the "registry" supports neither "include_tools" nor "enable_translation".')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && isset($v['text']) && '' === trim($v['text']);
                                })
                                ->thenInvalid('The "text" cannot be empty.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && isset($v['file']) && '' === trim($v['file']);
                                })
                                ->thenInvalid('The "file" cannot be empty.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && ($v['enabled'] ?? false) && !interface_exists(TranslatorInterface::class);
                                })
                                ->thenInvalid('System prompt translation is enabled, but no translator is present. Try running `composer require symfony/translation`.')
                            ->end()
                            ->children()
                                ->stringNode('text')
                                    ->info('The system prompt text')
                                ->end()
                                ->stringNode('file')
                                    ->info('Path to file containing the system prompt')
                                ->end()
                                ->stringNode('registry')
                                    ->info('Name of a versioned prompt of the "prompt_registry", resolved for every call')
                                ->end()
                                ->stringNode('version')
                                    ->info('Version of the registry prompt, the latest version when omitted')
                                ->end()
                                ->arrayNode('variables')
                                    ->info('Values of the placeholders of the registry prompt, e.g. "shop_name" for "{shop_name}"')
                                    ->useAttributeAsKey('name')
                                    ->normalizeKeys(false)
                                    ->scalarPrototype()->end()
                                ->end()
                                ->booleanNode('include_tools')
                                    ->info('Include tool definitions at the end of the system prompt')
                                    ->defaultFalse()
                                ->end()
                                ->booleanNode('enable_translation')
                                    ->info('Enable translation for the system prompt')
                                    ->defaultFalse()
                                ->end()
                                ->stringNode('translation_domain')
                                    ->info('The translation domain for the system prompt')
                                    ->defaultNull()
                                ->end()
                            ->end()
                        ->end()
                        ->append($tools)
                        ->booleanNode('include_sources')
                            ->info('Include sources exposed by tools as part of the tool result metadata')
                            ->defaultFalse()
                        ->end()
                        ->stringNode('message_store')
                            ->info('Service name of the message store continuing and saving the conversations of the agent by their ID')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('max_tool_calls')
                            ->info('Maximum number of tool calls per agent call, null to disable')
                            ->defaultValue(50)
                            ->validate()
                                ->ifTrue(static fn ($v) => null !== $v && !\is_int($v))
                                ->thenInvalid('The "max_tool_calls" option must be an integer or null, got %s.')
                            ->end()
                        ->end()
                        ->arrayNode('budget')
                            ->info('Stops a run gracefully once it spent more tokens or money, the prices come from the model catalog of the platform')
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => null === $v['max_tokens_per_run'] && null === $v['max_cost_per_run'])
                                ->thenInvalid('A budget needs "max_tokens_per_run" or "max_cost_per_run".')
                            ->end()
                            ->children()
                                ->integerNode('max_tokens_per_run')->min(1)->defaultNull()->end()
                                ->floatNode('max_cost_per_run')
                                    ->info('In the currency of the prices, USD for the prices of the bridges')
                                    ->min(0)
                                    ->defaultNull()
                                ->end()
                                ->stringNode('message')
                                    ->info('Answer of a stopped run, a generic apology when null')
                                    ->defaultNull()
                                ->end()
                            ->end()
                        ->end()
                        ->booleanNode('fault_tolerant_toolbox')
                            ->info('Continue the agent run even if a tool call fails')
                            ->defaultTrue()
                        ->end()
                        ->arrayNode('speech')
                            ->info('Speech (TTS/STT) decorator configuration')
                            ->treatFalseLike(['enabled' => false])
                            ->treatTrueLike(['enabled' => true])
                            ->treatNullLike(['enabled' => false])
                            ->children()
                                ->booleanNode('enabled')->defaultTrue()->end()
                                ->stringNode('text_to_speech_platform')
                                    ->info('Service name of the TTS platform (e.g. ai.platform.elevenlabs).')
                                    ->defaultNull()
                                ->end()
                                ->stringNode('speech_to_text_platform')
                                    ->info('Service name of the STT platform (e.g. ai.platform.openai).')
                                    ->defaultNull()
                                ->end()
                                ->stringNode('tts_model')
                                    ->info('Text-to-speech model name')
                                    ->defaultNull()
                                ->end()
                                ->variableNode('tts_options')
                                    ->info('Provider-specific TTS options')
                                    ->defaultValue([])
                                ->end()
                                ->stringNode('stt_model')
                                    ->info('Speech-to-text model name')
                                    ->defaultNull()
                                ->end()
                                ->variableNode('stt_options')
                                    ->info('Provider-specific STT options')
                                    ->defaultValue([])
                                ->end()
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => ($v['enabled'] ?? false) && null === ($v['text_to_speech_platform'] ?? null) && null === ($v['speech_to_text_platform'] ?? null))
                                ->thenInvalid('Either "text_to_speech_platform" or "speech_to_text_platform" must be configured when speech is enabled.')
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => ($v['enabled'] ?? false) && null === ($v['tts_model'] ?? null) && null === ($v['stt_model'] ?? null))
                                ->thenInvalid('At least one of "tts_model" or "stt_model" must be configured when speech is enabled.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('prompt_registry')
                ->info('Versioned prompts loaded from YAML files, requires "symfony/yaml"')
                ->children()
                    ->arrayNode('paths')
                        ->info('Directories containing one *.yaml file per prompt')
                        ->isRequired()
                        ->requiresAtLeastOneElement()
                        ->stringPrototype()->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('multi_agent')
                ->info('Multi-agent orchestration configuration')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->stringNode('orchestrator')
                            ->info('Service ID of the orchestrator agent')
                            ->isRequired()
                        ->end()
                        ->arrayNode('handoffs')
                            ->info('Handoff rules mapping agent service IDs to trigger keywords')
                            ->isRequired()
                            ->requiresAtLeastOneElement()
                            ->useAttributeAsKey('service')
                            ->arrayPrototype()
                                ->info('Keywords or phrases that trigger handoff to this agent')
                                ->requiresAtLeastOneElement()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                        ->stringNode('fallback')
                            ->info('Service ID of the fallback agent for unmatched requests')
                            ->isRequired()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('store')
                ->children()
                    ->append($import('store/azuresearch'))
                    ->append($import('store/cache'))
                    ->append($import('store/chromadb'))
                    ->append($import('store/clickhouse'))
                    ->append($import('store/cloudflare'))
                    ->append($import('store/doctrine'))
                    ->append($import('store/elasticsearch'))
                    ->append($import('store/manticoresearch'))
                    ->append($import('store/mariadb'))
                    ->append($import('store/meilisearch'))
                    ->append($import('store/memory'))
                    ->append($import('store/milvus'))
                    ->append($import('store/mongodb'))
                    ->append($import('store/neo4j'))
                    ->append($import('store/opensearch'))
                    ->append($import('store/pinecone'))
                    ->append($import('store/postgres'))
                    ->append($import('store/qdrant'))
                    ->append($import('store/redis'))
                    ->append($import('store/s3vectors'))
                    ->append($import('store/sqlite'))
                    ->append($import('store/supabase'))
                    ->append($import('store/surrealdb'))
                    ->append($import('store/typesense'))
                    ->append($import('store/weaviate'))
                    ->append($import('store/vektor'))
                ->end()
                ->validate()
                    ->always($assertUniqueNames('The stores "%s" have conflicting names, store names must be unique across all store types.'))
                ->end()
            ->end()
            ->arrayNode('message_store')
                ->children()
                    ->append($import('message_store/cache'))
                    ->append($import('message_store/cloudflare'))
                    ->append($import('message_store/doctrine'))
                    ->append($import('message_store/meilisearch'))
                    ->append($import('message_store/memory'))
                    ->append($import('message_store/mongodb'))
                    ->append($import('message_store/pogocache'))
                    ->append($import('message_store/redis'))
                    ->append($import('message_store/session'))
                    ->append($import('message_store/surrealdb'))
                ->end()
                ->validate()
                    ->always($assertUniqueNames('The message stores "%s" have conflicting names, message store names must be unique across all message store types.', ['doctrine' => 'dbal']))
                ->end()
            ->end()
            ->arrayNode('vectorizer')
                ->info('Vectorizers for converting strings to Vector objects and transforming TextDocument arrays to VectorDocument arrays')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->stringNode('platform')
                            ->info('Service name of platform')
                            ->defaultValue(PlatformInterface::class)
                        ->end()
                        ->variableNode('model')
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return !\is_string($v) && (!\is_array($v) || !isset($v['name']));
                                })
                                ->thenInvalid('Model must be a string or an array with a "name" key.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    // Check if both query parameters and options array are provided
                                    if (\is_array($v) && isset($v['name']) && isset($v['options']) && [] !== $v['options']) {
                                        return str_contains($v['name'], '?');
                                    }

                                    return false;
                                })
                                ->thenInvalid('Cannot use both query parameters in model name and options array.')
                            ->end()
                            ->beforeNormalization()
                                ->always(static function ($v) {
                                    if (\is_string($v) || null === $v) {
                                        return $v;
                                    }

                                    // It's an array with 'name' and optionally 'options'
                                    $model = $v['name'];
                                    $options = $v['options'] ?? [];

                                    // Parse query parameters from model name if present
                                    if (str_contains($model, '?')) {
                                        $parsed = parse_url($model);
                                        $model = $parsed['path'] ?? '';

                                        if ('' === $model) {
                                            throw new InvalidConfigurationException('Model name cannot be empty.');
                                        }

                                        if (isset($parsed['scheme'])) {
                                            $model = $parsed['scheme'].':'.$model;
                                        }

                                        if (isset($parsed['query'])) {
                                            // If options array is also provided, throw an error
                                            if ([] !== $options) {
                                                throw new InvalidConfigurationException('Cannot use both query parameters in model name and options array.');
                                            }
                                            $options = ModelOptionsParser::parse($parsed['query']);
                                        }
                                    }

                                    // Return model string with options as query parameters
                                    if ([] === $options) {
                                        return $model;
                                    }

                                    array_walk_recursive($options, static function (mixed &$value): void {
                                        if (\is_bool($value)) {
                                            $value = $value ? 'true' : 'false';
                                        }
                                    });

                                    return $model.'?'.str_replace('%', '%%', http_build_query($options));
                                })
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('indexer')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->stringNode('loader')
                            ->info('Service name of loader')
                            ->defaultNull()
                        ->end()
                        ->variableNode('source')
                            ->info('Source identifier (file path, URL, etc.) or array of sources')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('transformers')
                            ->info('Array of transformer service names')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('filters')
                            ->info('Array of filter service names')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->scalarNode('vectorizer')
                            ->info('Service name of vectorizer')
                            ->defaultValue(VectorizerInterface::class)
                        ->end()
                        ->stringNode('store')
                            ->info('Service name of store')
                            ->defaultValue(StoreInterface::class)
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('retriever')
                ->info('Retrievers for fetching documents from a vector store based on a query')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('vectorizer')
                            ->info('Service name of vectorizer')
                            ->defaultValue(VectorizerInterface::class)
                        ->end()
                        ->stringNode('store')
                            ->info('Service name of store')
                            ->defaultValue(StoreInterface::class)
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('feedback')
                ->info('Records user feedback and implicit signals on agent runs, requires "symfony/ai-feedback"')
                ->canBeEnabled()
                ->validate()
                    ->ifTrue(static fn (array $v): bool => $v['enabled'] && !isset($v['recorders']['langfuse']) && !isset($v['recorders']['phoenix']) && !isset($v['recorders']['doctrine']) && [] === $v['recorders']['services'])
                    ->thenInvalid('Configure at least one feedback recorder.')
                ->end()
                ->children()
                    ->arrayNode('recorders')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('langfuse')
                                ->info('Scores in Langfuse, requires "symfony/ai-langfuse-feedback-recorder"')
                                ->children()
                                    ->stringNode('public_key')->isRequired()->end()
                                    ->stringNode('secret_key')->isRequired()->end()
                                    ->stringNode('host')->defaultValue('https://cloud.langfuse.com')->end()
                                ->end()
                            ->end()
                            ->arrayNode('phoenix')
                                ->info('Trace annotations in Arize Phoenix, requires "symfony/ai-phoenix-feedback-recorder"')
                                ->children()
                                    ->stringNode('endpoint')->defaultValue('http://localhost:6006')->end()
                                    ->stringNode('api_key')->defaultNull()->end()
                                ->end()
                            ->end()
                            ->arrayNode('doctrine')
                                ->info('Rows in a database table, created with "ai:feedback:setup", requires "symfony/ai-doctrine-feedback-recorder"')
                                ->children()
                                    ->stringNode('connection')->defaultValue('default')->end()
                                    ->stringNode('table')->defaultValue('ai_feedback')->end()
                                ->end()
                            ->end()
                            ->arrayNode('services')
                                ->info('Service names of your own recorders')
                                ->stringPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->booleanNode('async')
                        ->info('Records the feedback in a Messenger handler, route "Symfony\\AI\\Feedback\\Messenger\\RecordFeedback" to an async transport')
                        ->defaultFalse()
                    ->end()
                    ->arrayNode('signals')
                        ->info('Implicit negative signals recorded on the run')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->booleanNode('tool_errors')->info('A tool call failed')->defaultTrue()->end()
                            ->booleanNode('guardrails')->info('A guardrail intervened')->defaultTrue()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('tracing')
                ->info('OpenTelemetry tracing of platforms, agents, toolboxes and retrievers, requires "symfony/ai-telemetry"')
                ->canBeEnabled()
                ->validate()
                    ->ifTrue(static fn (array $v): bool => null !== $v['tracer_provider'] && isset($v['exporter']))
                    ->thenInvalid('Configure either "tracer_provider" or "exporter" for tracing, not both.')
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $v): bool => null !== $v['user_id_resolver'] && !$v['capture_user'])
                    ->thenInvalid('The tracing "user_id_resolver" is only used with "capture_user" enabled.')
                ->end()
                ->children()
                    ->stringNode('tracer_provider')
                        ->info('Service name of the OpenTelemetry tracer provider, the globally registered one when null')
                        ->defaultNull()
                    ->end()
                    ->arrayNode('exporter')
                        ->info('Shortcut exporting to a tracing backend with OTLP over HTTP instead of a tracer provider, requires "open-telemetry/sdk" and "open-telemetry/exporter-otlp"')
                        ->validate()
                            ->ifTrue(static fn (array $v): bool => 1 !== \count(array_intersect(array_keys($v), ['otlp', 'langfuse', 'phoenix', 'jaeger', 'tempo'])))
                            ->thenInvalid('Configure exactly one tracing backend of "otlp", "langfuse", "phoenix", "jaeger" or "tempo".')
                        ->end()
                        ->children()
                            ->arrayNode('otlp')
                                ->info('Any backend ingesting OTLP over HTTP')
                                ->children()
                                    ->stringNode('endpoint')
                                        ->info('Base URL of the OTLP endpoint, "/v1/traces" is appended; export is disabled when empty')
                                        ->isRequired()
                                    ->end()
                                    ->arrayNode('headers')
                                        ->info('HTTP headers of the export requests, for example for authentication')
                                        ->useAttributeAsKey('name')
                                        ->normalizeKeys(false)
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->enumNode('protocol')
                                        ->values(['http/protobuf', 'http/json'])
                                        ->defaultValue('http/protobuf')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('langfuse')
                                ->info('Langfuse, requires "symfony/ai-langfuse-telemetry"')
                                ->children()
                                    ->stringNode('public_key')->isRequired()->end()
                                    ->stringNode('secret_key')->isRequired()->end()
                                    ->stringNode('host')
                                        ->info('Langfuse URL without "/api/public/otel"; export is disabled when empty')
                                        ->defaultValue('https://cloud.langfuse.com')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('phoenix')
                                ->info('Arize Phoenix, requires "symfony/ai-phoenix-telemetry"')
                                ->children()
                                    ->stringNode('endpoint')
                                        ->info('Export is disabled when empty')
                                        ->defaultValue('http://localhost:6006')
                                    ->end()
                                    ->stringNode('project')
                                        ->info('Project the traces are grouped by, the "default" project of Phoenix when null')
                                        ->defaultNull()
                                    ->end()
                                    ->stringNode('api_key')->defaultNull()->end()
                                ->end()
                            ->end()
                            ->arrayNode('jaeger')
                                ->info('Jaeger, requires "symfony/ai-jaeger-telemetry"')
                                ->children()
                                    ->stringNode('endpoint')
                                        ->info('OTLP/HTTP port of the collector; export is disabled when empty')
                                        ->defaultValue('http://localhost:4318')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('tempo')
                                ->info('Grafana Tempo, requires "symfony/ai-tempo-telemetry"')
                                ->children()
                                    ->stringNode('endpoint')
                                        ->info('OTLP/HTTP receiver of Tempo; export is disabled when empty')
                                        ->defaultValue('http://localhost:4318')
                                    ->end()
                                    ->stringNode('tenant')
                                        ->info('Sent as "X-Scope-OrgID" to a multi-tenant Tempo')
                                        ->defaultNull()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('resource_attributes')
                                ->info('OpenTelemetry resource attributes of the exported spans, like "service.name"')
                                ->useAttributeAsKey('name')
                                ->normalizeKeys(false)
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->booleanNode('capture_content')
                        ->info('Record prompts, completions, tool arguments and tool results, which may contain personal data')
                        ->defaultFalse()
                    ->end()
                    ->stringNode('content_redactor')
                        ->info('Service name of a "ContentRedactorInterface" masking personal data in the captured content and in recorded error messages')
                        ->defaultNull()
                    ->end()
                    ->booleanNode('capture_user')
                        ->info('Record the identifier of the authenticated user as "user.id", which may be personal data like an email address')
                        ->defaultFalse()
                    ->end()
                    ->stringNode('user_id_resolver')
                        ->info('Service name of a "UserIdResolverInterface" used with "capture_user" instead of the security user identifier, e.g. to record a pseudonymous ID')
                        ->defaultNull()
                    ->end()
                    ->arrayNode('instrument')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->booleanNode('platform')->defaultTrue()->end()
                            ->booleanNode('agent')->defaultTrue()->end()
                            ->booleanNode('toolbox')->defaultTrue()->end()
                            ->booleanNode('retriever')->defaultTrue()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end()
        ->validate()
            ->ifTrue(static function ($v) {
                if (!isset($v['agent']) || !isset($v['multi_agent'])) {
                    return false;
                }

                $agentNames = array_keys($v['agent']);
                $multiAgentNames = array_keys($v['multi_agent']);
                $duplicates = array_intersect($agentNames, $multiAgentNames);

                return [] !== $duplicates;
            })
            ->then(static function ($v) {
                $agentNames = array_keys($v['agent'] ?? []);
                $multiAgentNames = array_keys($v['multi_agent'] ?? []);
                $duplicates = array_intersect($agentNames, $multiAgentNames);

                throw new InvalidArgumentException(\sprintf('Agent names and multi-agent names must be unique. Duplicate name(s) found: "%s"', implode(', ', $duplicates)));
            })
        ->end()
        ->validate()
            ->ifTrue(static function ($v) {
                if (!isset($v['multi_agent']) || !isset($v['agent'])) {
                    return false;
                }

                $agentNames = array_keys($v['agent']);

                foreach ($v['multi_agent'] as $multiAgentName => $multiAgent) {
                    // Check orchestrator exists
                    if (!\in_array($multiAgent['orchestrator'], $agentNames, true)) {
                        return true;
                    }

                    // Check fallback exists
                    if (!\in_array($multiAgent['fallback'], $agentNames, true)) {
                        return true;
                    }

                    // Check handoff agents exist
                    foreach (array_keys($multiAgent['handoffs']) as $handoffAgent) {
                        if (!\in_array($handoffAgent, $agentNames, true)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->then(static function ($v) {
                $agentNames = array_keys($v['agent']);

                foreach ($v['multi_agent'] as $multiAgentName => $multiAgent) {
                    if (!\in_array($multiAgent['orchestrator'], $agentNames, true)) {
                        throw new InvalidArgumentException(\sprintf('The agent "%s" referenced in multi-agent "%s" as orchestrator does not exist', $multiAgent['orchestrator'], $multiAgentName));
                    }

                    if (!\in_array($multiAgent['fallback'], $agentNames, true)) {
                        throw new InvalidArgumentException(\sprintf('The agent "%s" referenced in multi-agent "%s" as fallback does not exist', $multiAgent['fallback'], $multiAgentName));
                    }

                    foreach (array_keys($multiAgent['handoffs']) as $handoffAgent) {
                        if (!\in_array($handoffAgent, $agentNames, true)) {
                            throw new InvalidArgumentException(\sprintf('The agent "%s" referenced in multi-agent "%s" as handoff target does not exist', $handoffAgent, $multiAgentName));
                        }
                    }
                }
            })
        ->end()
    ;
};
