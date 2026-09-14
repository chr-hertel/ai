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

use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * Client for one API, whatever its transport: builds the request, sends it and converts the response.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ApiClientInterface extends ResultConverterInterface
{
    /**
     * Whether this client serves the given model.
     */
    public function supports(Model $model): bool;

    /**
     * Sends the request for the given model and payload.
     *
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface;
}
