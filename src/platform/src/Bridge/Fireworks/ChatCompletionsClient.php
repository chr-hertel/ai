<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks;

use Symfony\AI\Platform\Bridge\Generic\ChatCompletionsClient as GenericChatCompletionsClient;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidRequestException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChatCompletionsClient extends GenericChatCompletionsClient
{
    public function __construct(HttpTransport $transport)
    {
        // Fireworks speaks the Chat Completions API natively, so it gets no gateway defaults.
        parent::__construct($transport, modelClass: Fireworks::class, applyGatewayDefaults: false);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Fireworks && $model->supports(Capability::INPUT_MESSAGES);
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return parent::convert($raw, $options);
        }

        $this->transport->throwOnError($raw, $options);

        $data = $raw->getData();

        // Fireworks reports a rejected request through the body of an otherwise successful response.
        $error = \is_array($data['error'] ?? null) ? $data['error'] : [];

        if ('invalid_request_error' === ($error['code'] ?? null)) {
            throw new InvalidRequestException(\is_string($error['message'] ?? null) ? $error['message'] : 'Invalid request');
        }

        return parent::convert($raw, $options);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    protected function convertStreamUsage(array $usage, ?string $model = null): TokenUsage
    {
        return $this->getTokenUsageExtractor()->extractFromArray($usage, $model);
    }
}
