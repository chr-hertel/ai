<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Nova;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use AsyncAws\BedrockRuntime\Input\InvokeModelRequest;
use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\Bedrock\FinishReasonMapper;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Bridge\Bedrock\RegionMapper;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FinishReason\FinishReasonAwareTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Björn Altmann
 */
final class InvokeClient implements ApiClientInterface
{
    use FinishReasonAwareTrait;

    public function __construct(
        private readonly BedrockRuntimeClient $bedrockRuntimeClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Nova;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawBedrockResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        unset($payload['model']);

        $modelOptions = [];
        if (isset($options['tools'])) {
            $modelOptions['toolConfig']['tools'] = $options['tools'];
        }

        if (isset($options['temperature'])) {
            $modelOptions['inferenceConfig']['temperature'] = $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $modelOptions['inferenceConfig']['maxTokens'] = $options['max_tokens'];
        }

        $request = [
            'modelId' => $this->getModelId($model),
            'contentType' => 'application/json',
            'body' => json_encode(array_merge($payload, $modelOptions), \JSON_THROW_ON_ERROR),
        ];

        return new RawBedrockResult($this->bedrockRuntimeClient->invokeModel(new InvokeModelRequest($request)));
    }

    public function convert(RawResultInterface|RawBedrockResult $result, array $options = []): ToolCallResult|TextResult
    {
        $data = $result->getData();

        if (!isset($data['output']) || [] === $data['output']) {
            throw new RuntimeException('Response does not contain any content.');
        }

        if (!isset($data['output']['message']['content'][0]['text'])) {
            throw new RuntimeException('Response content does not contain any text.');
        }

        $toolCalls = [];
        foreach ($data['output']['message']['content'] as $content) {
            if (isset($content['toolUse'])) {
                $toolCalls[] = new ToolCall($content['toolUse']['toolUseId'], $content['toolUse']['name'], $content['toolUse']['input']);
            }
        }
        if ([] !== $toolCalls) {
            return $this->withFinishReason(new ToolCallResult($toolCalls), FinishReasonMapper::map($data['stopReason'] ?? null));
        }

        return $this->withFinishReason(new TextResult($data['output']['message']['content'][0]['text']), FinishReasonMapper::map($data['stopReason'] ?? null));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    private function getModelId(Model $model): string
    {
        $configuredRegion = $this->bedrockRuntimeClient->getConfiguration()->get('region');
        $regionPrefix = RegionMapper::map((string) $configuredRegion);

        return $regionPrefix.'.amazon.'.$model->getName().'-v1:0';
    }
}
