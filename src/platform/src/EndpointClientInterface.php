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

/**
 * Self-contained client for a single endpoint contract — owns the request
 * shape, the transport call, and the response shape for that one contract.
 *
 * A {@see Provider} is wired with the clients it speaks, and each client
 * answers {@see supports()} for the model family it serves: by class where the
 * bridge has dedicated model classes, by capability where one class covers
 * several contracts. Nothing is precomputed onto the {@see Model}, so a model
 * built by hand dispatches like one built by a catalog. Where several clients
 * accept the same model, the provider's client order decides, and
 * `$options['endpoint']` picks one explicitly.
 *
 * Extends the legacy {@see ModelClientInterface} and
 * {@see ResultConverterInterface} so a single client instance plugs into
 * the existing {@see Provider} dispatch loops on both sides.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface EndpointClientInterface extends ModelClientInterface, ResultConverterInterface
{
    /**
     * Identifier matched against `$options['endpoint']` when an invocation asks
     * for a specific contract. Convention: "{vendor}.{contract}", e.g.
     * "voyage.embeddings".
     *
     * @return non-empty-string
     */
    public function endpoint(): string;
}
