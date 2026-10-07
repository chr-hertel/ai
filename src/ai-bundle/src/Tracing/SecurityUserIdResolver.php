<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tracing;

use Symfony\AI\OpenTelemetryBridge\UserIdResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Records the identifier of the authenticated Symfony user.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SecurityUserIdResolver implements UserIdResolverInterface
{
    public function __construct(
        private readonly ?TokenStorageInterface $tokenStorage,
    ) {
    }

    public function resolve(): ?string
    {
        return $this->tokenStorage?->getToken()?->getUser()?->getUserIdentifier();
    }
}
