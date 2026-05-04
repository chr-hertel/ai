<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter as OpenResponsesResultConverter;
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\TransportInterface;

/**
 * OpenAI /v1/responses (Responses API) contract handler.
 *
 * Owns the request reshape for structured output (response_format → text.format).
 * Response parsing and SSE streaming are identical to the generic Responses API,
 * so they are delegated to the OpenResponses converter. HTTP status mapping
 * (401/400/429/5xx) is delegated to {@see HttpTransport}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResponsesClient implements EndpointClientInterface
{
    public const ENDPOINT = 'openai.responses';

    private readonly OpenResponsesResultConverter $resultConverter;

    public function __construct(
        private readonly TransportInterface $transport,
    ) {
        $this->resultConverter = new OpenResponsesResultConverter();
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        // OpenAI performs automatic prompt caching; cacheRetention is not an
        // OpenAI concept and would be rejected by the Responses API.
        unset($options['cacheRetention']);

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $schema = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema'];
            $options['text']['format'] = $schema;
            $options['text']['format']['name'] = $schema['name'];
            $options['text']['format']['type'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['type'];

            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        $envelope = new RequestEnvelope(
            payload: array_merge($options, ['model' => $model->getName()], $payload),
            path: '/v1/responses',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        return $this->resultConverter->convert($raw, $options);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }
}
