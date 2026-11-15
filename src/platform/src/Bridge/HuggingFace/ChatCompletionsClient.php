<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Bridge\Generic\ChatCompletionsClient as GenericChatCompletionsClient;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChatCompletionsClient extends GenericChatCompletionsClient
{
    /**
     * @param ?string $provider Inference provider to route requests to, Hugging Face picks one when null
     */
    public function __construct(
        HttpTransport $transport,
        private readonly ?string $provider = null,
    ) {
        parent::__construct($transport, modelClass: Completions::class);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $provider = $options['provider'] ?? $this->provider;
        unset($options['provider']);

        // The router maps the Hugging Face model ID to the provider's own ID via the "<model>:<provider>" suffix
        if (null !== $provider && !str_contains($model->getName(), ':')) {
            $model = new Completions(\sprintf('%s:%s', $model->getName(), $provider), $model->getCapabilities(), $model->getOptions());
        }

        return parent::request($model, $payload, $options);
    }
}
