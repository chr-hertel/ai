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

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\TransportInterface;
use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter as OpenResponsesResultConverter;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResponsesClient implements ApiClientInterface
{
    private readonly OpenResponsesResultConverter $resultConverter;

    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $modelClass = Gpt::class,
    ) {
        $this->resultConverter = new OpenResponsesResultConverter();
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

        // OpenAI performs automatic prompt caching; no explicit cache_control
        // annotation is needed and cacheRetention is not an OpenAI concept.
        // Strip it so it is never forwarded to the Responses API.
        unset($options['cacheRetention']);

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $schema = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema'];
            $options['text']['format'] = $schema;
            $options['text']['format']['name'] = $schema['name'];
            $options['text']['format']['type'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['type'];

            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        return $this->transport->send('/v1/responses', array_merge($options, ['model' => $model->getName()], $payload));
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        $this->transport->throwOnError($raw, $options);

        return $this->resultConverter->convert($raw, $options);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }
}
