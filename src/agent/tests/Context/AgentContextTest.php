<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Context;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\MockAgent;

final class AgentContextTest extends TestCase
{
    public function testItHasNoDelegationByDefault()
    {
        $this->assertNull((new AgentContext(new MockAgent()))->getDelegation());
    }

    public function testItHoldsTheDelegatedExecution()
    {
        $context = new AgentContext(new MockAgent());
        $execution = new Execution(static fn (): \Generator => yield from []);

        $context->delegate($execution);

        $this->assertSame($execution, $context->getDelegation());
    }

    public function testARunCanOnlyBeDelegatedOnce()
    {
        $context = new AgentContext(new MockAgent());
        $context->delegate(new Execution(static fn (): \Generator => yield from []));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The run has already been delegated.');

        $context->delegate(new Execution(static fn (): \Generator => yield from []));
    }
}
