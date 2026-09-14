<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Anthropic\Transport;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use AsyncAws\BedrockRuntime\Input\InvokeModelRequest;
use Symfony\AI\Platform\Bridge\Anthropic\Transport\TransportInterface;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Bridge\Bedrock\RegionMapper;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Björn Altmann
 */
final class BedrockTransport implements TransportInterface
{
    /**
     * Bedrock model identifiers differ from Anthropic API names — some require version suffixes,
     * others don't. See https://platform.claude.com/docs/en/about-claude/models/overview for details.
     *
     * @var array<string, string>
     */
    private const MODEL_MAP = [
        'claude-opus-4-7' => 'claude-opus-4-7',
        'claude-sonnet-4-6' => 'claude-sonnet-4-6',
        'claude-opus-4-6' => 'claude-opus-4-6-v1',
        'claude-haiku-4-5-20251001' => 'claude-haiku-4-5-20251001-v1:0',
        'claude-sonnet-4-5-20250929' => 'claude-sonnet-4-5-20250929-v1:0',
        'claude-opus-4-5-20251101' => 'claude-opus-4-5-20251101-v1:0',
        'claude-opus-4-1-20250805' => 'claude-opus-4-1-20250805-v1:0',
        'claude-sonnet-4-20250514' => 'claude-sonnet-4-20250514-v1:0',
        'claude-opus-4-20250514' => 'claude-opus-4-20250514-v1:0',
        'claude-3-sonnet-20240229' => 'claude-3-sonnet-20240229-v1:0',
        'claude-3-haiku-20240307' => 'claude-3-haiku-20240307-v1:0',
        'claude-3-5-haiku-20241022' => 'claude-3-5-haiku-20241022-v1:0',
    ];

    /**
     * @var array<string, string>
     */
    private readonly array $modelMap;

    /**
     * @param array<string, string> $modelOverrides additional or overriding entries for the model ID map,
     *                                              keyed by Anthropic model name with Bedrock model ID as value
     */
    public function __construct(
        private readonly BedrockRuntimeClient $bedrockRuntimeClient,
        private readonly string $version = '2023-05-31',
        array $modelOverrides = [],
    ) {
        $this->modelMap = array_replace(self::MODEL_MAP, $modelOverrides);
    }

    public function send(Model $model, string $path, array $payload, array $headers = []): RawResultInterface
    {
        unset($payload['model']);

        if (!isset($payload['anthropic_version'])) {
            $payload['anthropic_version'] = 'bedrock-'.$this->version;
        }

        $request = [
            'modelId' => $this->getModelId($model),
            'contentType' => 'application/json',
            'body' => json_encode($payload, \JSON_THROW_ON_ERROR),
        ];

        return new RawBedrockResult($this->bedrockRuntimeClient->invokeModel(new InvokeModelRequest($request)));
    }

    public function throwOnError(RawResultInterface $result, array $options = []): void
    {
    }

    private function getModelId(Model $model): string
    {
        $regionPrefix = RegionMapper::map((string) $this->bedrockRuntimeClient->getConfiguration()->get('region'));
        $name = $model->getName();

        return $regionPrefix.'.anthropic.'.($this->modelMap[$name] ?? $name);
    }
}
