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
use Symfony\AI\Platform\Bridge\OpenAi\Transport\TransportInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RealtimeSessionResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Saiful Islam Feroz <saiful.feroz@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RealtimeSessionClient implements ApiClientInterface
{
    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Realtime;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $session = array_merge($model->getOptions(), $options);
        $session['model'] = $model->getName();

        if (\is_array($payload)) {
            $session = array_merge($session, $payload);
        } elseif ('' !== trim($payload)) {
            $session['instructions'] = $payload;
        }

        if (!isset($session['type'])) {
            $session['type'] = 'realtime';
        }

        if (isset($session['modalities'])) {
            if (!isset($session['output_modalities'])) {
                $session['output_modalities'] = $session['modalities'];
            }
            unset($session['modalities']);
        } elseif (!isset($session['output_modalities'])) {
            $session['output_modalities'] = ['text', 'audio'];
        }

        if (isset($session['voice'])) {
            $session['audio']['output']['voice'] = $session['voice'];
            unset($session['voice']);
        } elseif (!isset($session['audio']['output']['voice'])) {
            $session['audio']['output']['voice'] = 'alloy';
        }

        return $this->transport->send('/v1/realtime/client_secrets', ['session' => $session]);
    }

    public function convert(RawResultInterface $result, array $options = []): RealtimeSessionResult
    {
        $this->transport->throwOnError($result, $options);

        if (!$result instanceof RawHttpResult) {
            throw new RuntimeException(\sprintf('"%s" requires an HTTP-backed raw result, got "%s".', self::class, $result::class));
        }

        $response = $result->getObject();

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('The OpenAI Realtime API returned an error: "%s"', $response->getContent(false)));
        }

        $data = $response->toArray();

        return new RealtimeSessionResult(
            id: $data['session']['id'] ?? ($data['id'] ?? ''),
            clientSecret: $data['value'] ?? ($data['client_secret']['value'] ?? ($data['client_secret'] ?? '')),
            expiresAt: (int) ($data['expires_at'] ?? ($data['client_secret']['expires_at'] ?? 0)),
            model: $data['session']['model'] ?? ($data['model'] ?? ''),
            voice: $data['session']['audio']['output']['voice'] ?? ($data['session']['voice'] ?? ($data['voice'] ?? null)),
            modalities: $data['session']['output_modalities'] ?? ($data['session']['modalities'] ?? ($data['output_modalities'] ?? ($data['modalities'] ?? ['text', 'audio']))),
            raw: $data,
        );
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
