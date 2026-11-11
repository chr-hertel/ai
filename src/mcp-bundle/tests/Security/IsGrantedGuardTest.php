<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Security;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Security\IsGrantedGuard;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class IsGrantedGuardTest extends TestCase
{
    public function testGrantsElementsWithoutAttributes()
    {
        $guard = new IsGrantedGuard($this->checker([]), ['tool' => ['submit' => [GuardedTools::class, 'submit']]]);

        $this->assertTrue($guard->isGranted(new Tool('other', null, ['type' => 'object'], null, null)));
        $this->assertTrue($guard->isGranted(new ResourceTemplate('doc://{id}', 'doc')));
    }

    public function testRequiresTheClassAndTheMethodAttributes()
    {
        $handlers = ['tool' => ['submit' => [GuardedTools::class, 'submit']]];
        $tool = new Tool('submit', null, ['type' => 'object'], null, null);

        $this->assertTrue((new IsGrantedGuard($this->checker(['ROLE_USER', 'OAUTH2_SCOPE(proposals:write)']), $handlers))->isGranted($tool));
        $this->assertFalse((new IsGrantedGuard($this->checker(['ROLE_USER']), $handlers))->isGranted($tool));
        $this->assertFalse((new IsGrantedGuard($this->checker(['OAUTH2_SCOPE(proposals:write)']), $handlers))->isGranted($tool));
    }

    public function testDenialCarriesTheAttributeForTheFirewall()
    {
        $guard = new IsGrantedGuard($this->checker(['ROLE_USER']), ['tool' => ['submit' => [GuardedTools::class, 'submit']]]);

        try {
            $guard->denyAccessUnlessGranted(new Tool('submit', null, ['type' => 'object'], null, null));
            $this->fail('Access should be denied.');
        } catch (AccessDeniedException $e) {
            $this->assertSame(['OAUTH2_SCOPE(proposals:write)'], $e->getAttributes());
        }
    }

    public function testDenialUsesTheStatusCodeOfTheAttribute()
    {
        $guard = new IsGrantedGuard($this->checker(['ROLE_USER']), ['prompt' => ['review' => [GuardedTools::class, 'review']]]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Reviewers only.');

        $guard->denyAccessUnlessGranted(new Prompt('review'));
    }

    /**
     * @param list<string> $granted
     */
    private function checker(array $granted): AuthorizationCheckerInterface
    {
        return new class($granted) implements AuthorizationCheckerInterface {
            /**
             * @param list<string> $granted
             */
            public function __construct(private readonly array $granted)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return \in_array($attribute, $this->granted, true);
            }
        };
    }
}

#[IsGranted('ROLE_USER')]
final class GuardedTools
{
    #[McpTool(name: 'submit')]
    #[IsGranted('OAUTH2_SCOPE(proposals:write)')]
    public function submit(): string
    {
        return 'submitted';
    }

    /**
     * @return list<mixed>
     */
    #[McpPrompt(name: 'review')]
    #[IsGranted('ROLE_REVIEWER', message: 'Reviewers only.', statusCode: 404)]
    public function review(): array
    {
        return [];
    }
}
