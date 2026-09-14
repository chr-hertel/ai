<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TextToSpeechClient extends AbstractVeniceClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Venice && $model->supports(Capability::TEXT_TO_SPEECH);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return $this->postJson('audio/speech', [
            'response_format' => 'mp3',
            ...$options,
            'input' => (new VenicePayload($payload))->asTextToSpeechPayload(),
            'model' => $model->getName(),
        ]);
    }

    public function convert(RawResultInterface $result, array $options = []): BinaryResult
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        return new BinaryResult($response->getContent());
    }
}
