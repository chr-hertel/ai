<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * A self-contained handler for a single provider endpoint.
 *
 * It owns request building, HTTP transport, and response conversion in one
 * place, returning a ResultInterface directly. This is a slim, additive
 * alternative to the ModelClientInterface + ResultConverterInterface pair:
 * a Provider can be configured with endpoint handlers, falling back to the
 * legacy model clients and result converters when no handler matches.
 *
 * Handlers route by Model type and an optional task discriminator, which is
 * passed through $options['task'].
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface EndpointHandlerInterface
{
    /**
     * @param non-empty-string|null $task The task discriminator (e.g. Whisper\Task::TRANSLATION),
     *                                    or null for the model's default endpoint
     */
    public function supports(Model $model, ?string $task = null): bool;

    /**
     * Builds the request, performs the HTTP call and converts the response.
     *
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     *
     * @throws ExceptionInterface
     */
    public function request(Model $model, array|string $payload, array $options = []): ResultInterface;
}
