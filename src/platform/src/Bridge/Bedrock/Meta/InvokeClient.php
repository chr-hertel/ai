<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Meta;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use AsyncAws\BedrockRuntime\Input\InvokeModelRequest;
use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\Bedrock\FinishReasonMapper;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Bridge\Bedrock\RegionMapper;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FinishReason\FinishReasonAwareTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
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
        return $model instanceof Llama;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawBedrockResult
    {
        return new RawBedrockResult($this->bedrockRuntimeClient->invokeModel(new InvokeModelRequest([
            'modelId' => $this->getModelId($model),
            'contentType' => 'application/json',
            'body' => json_encode($payload, \JSON_THROW_ON_ERROR),
        ])));
    }

    public function convert(RawResultInterface|RawBedrockResult $result, array $options = []): TextResult
    {
        $data = $result->getData();

        if (!isset($data['generation'])) {
            throw new RuntimeException('Response does not contain any content.');
        }

        return $this->withFinishReason(new TextResult($data['generation']), FinishReasonMapper::map($data['stop_reason'] ?? null));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    private function getModelId(Model $model): string
    {
        $configuredRegion = $this->bedrockRuntimeClient->getConfiguration()->get('region');
        $regionPrefix = RegionMapper::map((string) $configuredRegion);
        $modifiedModelName = str_replace('llama-3', 'llama3', $model->getName());

        return $regionPrefix.'.meta.'.str_replace('.', '-', $modifiedModelName).'-v1:0';
    }
}
