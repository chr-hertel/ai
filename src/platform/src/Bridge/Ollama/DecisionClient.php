<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Bridge\Decision\AnswersConverter;
use Symfony\AI\Platform\Bridge\Decision\RequestBodyBuilder;
use Symfony\AI\Platform\Bridge\Decision\TokenUsageExtractor;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Sends {@see \Symfony\AI\Platform\Bridge\Decision\Evaluation}s to the "POST /v1/systemone" endpoint of Ollama
 * and converts the response into {@see \Symfony\AI\Platform\Bridge\Decision\Answer\Answers}.
 *
 * Ollama reports errors as {"error": "message"}; the success payload is shared with other decision providers.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DecisionClient extends AbstractOllamaClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Decision;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!class_exists(RequestBodyBuilder::class)) {
            throw new RuntimeException(\sprintf('The model "%s" is a decision model, which requires the Decision bridge. Try running "composer require symfony/ai-decision-platform".', $model->getName()));
        }

        return new RawHttpResult($this->httpClient->request('POST', 'v1/systemone', ['json' => RequestBodyBuilder::build($model, $payload, supportsImages: $model->supports(Capability::INPUT_IMAGE))]));
    }

    public function convert(RawResultInterface $result, array $options = []): ObjectResult
    {
        $httpResponse = $result->getObject();

        $this->throwOnError($result);

        if (200 !== $httpResponse->getStatusCode()) {
            // e.g. 413 when the request exceeds the size limit
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $this->extractErrorMessage($httpResponse) ?? $httpResponse->getContent(false)));
        }

        return new ObjectResult((new AnswersConverter())->convert($result->getData()));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }
}
