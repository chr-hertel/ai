<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Decision;

use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\OpenAi\AbstractModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Decision;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends {@see Evaluation}s to the "POST /v1/decisions" endpoint of OpenAI.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient extends AbstractModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
    ) {
        self::validateApiKey($apiKey);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Decision;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!\is_array($payload) || !isset($payload['questions'])) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" requires an "%s" input, try running "composer require symfony/ai-decision-platform".', $model->getName(), Evaluation::class));
        }

        return new RawHttpResult($this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/decisions', [
            'auth_bearer' => $this->apiKey,
            'json' => ['model' => $model->getName()] + $payload,
        ]));
    }
}
