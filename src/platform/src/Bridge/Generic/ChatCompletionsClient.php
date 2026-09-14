<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\Generic\Completions\CompletionsConversionTrait;
use Symfony\AI\Platform\Bridge\Generic\Completions\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * This default implementation is based on OpenAI's initial completion endpoint, that got later adopted by other
 * providers as well. It can be used by any bridge or directly with the default Factory.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
class ChatCompletionsClient implements ApiClientInterface
{
    use CompletionsConversionTrait;

    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        protected readonly HttpTransport $transport,
        private readonly string $path = '/v1/chat/completions',
        private readonly string $modelClass = CompletionsModel::class,
        private readonly bool $applyGatewayDefaults = true,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof $this->modelClass;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        // Request usage stats for streamed responses by default,
        // but preserve explicit stream_options when provided.
        if ($this->applyGatewayDefaults && ($options['stream'] ?? false)) {
            if (!\array_key_exists('stream_options', $options)) {
                $options['stream_options'] = ['include_usage' => true];
            }
        }

        // Some OpenAI-compatible providers do not default "tool_choice" to "auto" server-side,
        // which makes the model answer with a text response instead of a proper tool call.
        if ($this->applyGatewayDefaults && [] !== ($options['tools'] ?? [])) {
            $options['tool_choice'] ??= 'auto';
        }

        // cacheRetention is an internal Symfony AI option consumed by PromptCacheNormalizer
        // (Anthropic-only).  Strip it here so it is never forwarded to OpenAI-compatible
        // endpoints, which reject unknown request body fields with a 400 error.
        unset($options['cacheRetention']);

        return $this->transport->send($this->path, array_merge($options, $payload, ['model' => $model->getName()]));
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        $this->transport->throwOnError($raw, $options);

        if ($options['stream'] ?? false) {
            $this->throwOnErrorStatus($raw);

            return new StreamResult($this->convertStream($raw));
        }

        $data = $raw->getData();

        if (isset($data['error']['code']) && 'content_filter' === $data['error']['code']) {
            throw new ContentFilterException($data['error']['message']);
        }

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error "%s"-%s (%s): "%s".', $data['error']['code'] ?? '-', $data['error']['type'] ?? '-', $data['error']['param'] ?? '-', $data['error']['message'] ?? '-'));
        }

        if (!isset($data['choices'])) {
            throw new RuntimeException('Response does not contain choices.');
        }

        $choices = array_map($this->convertChoice(...), $data['choices']);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    protected function throwOnErrorStatus(RawResultInterface $raw): void
    {
        $response = $raw->getObject();

        if ($response instanceof ResponseInterface && ($code = $response->getStatusCode()) >= 400) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $code, $response->getContent(false)));
        }
    }
}
