<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge;

/**
 * Provides the identifier of the user the traced call runs for, recorded as "user.id".
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface UserIdResolverInterface
{
    /**
     * @return non-empty-string|null null when there is no user, e.g. for anonymous requests
     */
    public function resolve(): ?string;
}
