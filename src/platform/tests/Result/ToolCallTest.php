<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallState;

final class ToolCallTest extends TestCase
{
    public function testToolCall()
    {
        $toolCall = new ToolCall('id', 'name', ['foo' => 'bar']);
        $this->assertSame('id', $toolCall->getId());
        $this->assertSame('name', $toolCall->getName());
        $this->assertSame(['foo' => 'bar'], $toolCall->getArguments());
    }

    public function testGetId()
    {
        $toolCall = new ToolCall('test-id', 'function-name');
        $this->assertSame('test-id', $toolCall->getId());
    }

    public function testGetName()
    {
        $toolCall = new ToolCall('id', 'test-function');
        $this->assertSame('test-function', $toolCall->getName());
    }

    public function testGetArguments()
    {
        $arguments = ['param1' => 'value1', 'param2' => 42];
        $toolCall = new ToolCall('id', 'name', $arguments);
        $this->assertSame($arguments, $toolCall->getArguments());
    }

    public function testGetArgumentsReturnsEmptyArrayByDefault()
    {
        $toolCall = new ToolCall('id', 'name');
        $this->assertSame([], $toolCall->getArguments());
    }

    public function testToolCallIsRequestedByDefault()
    {
        $toolCall = new ToolCall('id', 'name');

        $this->assertSame(ToolCallState::Requested, $toolCall->getState());
        $this->assertFalse($toolCall->isSuspended());
        $this->assertNull($toolCall->getReason());
    }

    public function testSuspendReturnsASuspendedCopy()
    {
        $toolCall = new ToolCall('id', 'name', ['path' => 'notes.txt'], 'signature');
        $suspended = $toolCall->suspend('Needs approval.');

        $this->assertFalse($toolCall->isSuspended());
        $this->assertTrue($suspended->isSuspended());
        $this->assertSame(ToolCallState::Suspended, $suspended->getState());
        $this->assertSame('Needs approval.', $suspended->getReason());
        $this->assertSame('id', $suspended->getId());
        $this->assertSame('name', $suspended->getName());
        $this->assertSame(['path' => 'notes.txt'], $suspended->getArguments());
        $this->assertSame('signature', $suspended->getSignature());
    }

    public function testResumeReturnsARequestedCopy()
    {
        $suspended = (new ToolCall('id', 'name', ['path' => 'notes.txt'], 'signature'))->suspend('Needs approval.');

        $resumed = $suspended->resume();
        $this->assertSame(ToolCallState::Requested, $resumed->getState());
        $this->assertNull($resumed->getReason());
        $this->assertSame(['path' => 'notes.txt'], $resumed->getArguments());
        $this->assertSame('signature', $resumed->getSignature());

        $this->assertSame(['path' => 'archive/notes.txt'], $suspended->resume(['path' => 'archive/notes.txt'])->getArguments());
    }
}
