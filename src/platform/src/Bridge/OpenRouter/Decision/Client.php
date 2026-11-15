<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Decision;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\Decision\AnswersConverter;
use Symfony\AI\Platform\Bridge\Decision\RequestBodyBuilder;
use Symfony\AI\Platform\Bridge\Decision\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\OpenRouter\DecisionModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends {@see \Symfony\AI\Platform\Bridge\Decision\Evaluation}s to the "POST /alpha/decisions" endpoint of OpenRouter
 * and converts the response into {@see \Symfony\AI\Platform\Bridge\Decision\Answer\Answers}.
 *
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class Client implements ApiClientInterface
{
    use HttpStatusErrorHandlingTrait;

    private const FORWARDED_OPTIONS = ['provider', 'session_id', 'trace', 'user'];

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of an OpenRouter-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://openrouter.ai/api',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof DecisionModel;
    }

    /**
     * @param array{provider?: array<string, mixed>, session_id?: string, trace?: array<string, mixed>, user?: string} $options
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!class_exists(RequestBodyBuilder::class)) {
            throw new RuntimeException(\sprintf('The model "%s" is a decision model, which requires the Decision bridge. Try running "composer require symfony/ai-decision-platform".', $model->getName()));
        }

        $body = RequestBodyBuilder::build($model, $payload);

        foreach ((array) $body['questions'] as $id => $question) {
            if ('noul' !== ($question['type'] ?? null) || !isset($question['criteria'])) {
                continue;
            }

            if (!isset($question['criteria']->true, $question['criteria']->false)) {
                throw new InvalidArgumentException(\sprintf('The noul question "%s" requires both a "true" and a "false" criterion, or none, for OpenRouter decision models.', $id));
            }
        }

        foreach (self::FORWARDED_OPTIONS as $option) {
            if (isset($options[$option])) {
                $body[$option] = $options[$option];
            }
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/alpha/decisions', [
            'auth_bearer' => $this->apiKey,
            'json' => $body,
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): ObjectResult
    {
        $httpResponse = $result->getObject();

        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            // e.g. 402 for insufficient credits or 413 for a payload exceeding the size limit
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $this->extractErrorMessage($httpResponse) ?? $httpResponse->getContent(false)));
        }

        return new ObjectResult((new AnswersConverter())->convert($result->getData()));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }
}
