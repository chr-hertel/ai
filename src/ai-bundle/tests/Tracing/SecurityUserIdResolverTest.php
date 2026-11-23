<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\Tracing\SecurityUserIdResolver;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class SecurityUserIdResolverTest extends TestCase
{
    public function testIdentifierOfTheAuthenticatedUser()
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('jane@example.com', null), 'main'));

        $this->assertSame('jane@example.com', (new SecurityUserIdResolver($tokenStorage))->resolve());
    }

    public function testNoUserWithoutToken()
    {
        $this->assertNull((new SecurityUserIdResolver(new TokenStorage()))->resolve());
    }

    public function testNoUserForAnonymousToken()
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new NullToken());

        $this->assertNull((new SecurityUserIdResolver($tokenStorage))->resolve());
    }

    public function testNoUserWithoutSecurity()
    {
        $this->assertNull((new SecurityUserIdResolver(null))->resolve());
    }
}
