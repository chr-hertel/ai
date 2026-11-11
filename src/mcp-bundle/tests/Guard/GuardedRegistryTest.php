<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Guard;

use Mcp\Capability\Registry;
use Mcp\Event\ToolListChangedEvent;
use Mcp\Exception\InvalidCursorException;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Mcp\Server;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\McpBundle\Guard\GuardedRegistry;
use Symfony\AI\McpBundle\Guard\McpGuardInterface;

final class GuardedRegistryTest extends TestCase
{
    public function testTheBuilderLoadAnnouncesNoListChange()
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /** @var list<class-string> */
            private array $events = [];

            public function dispatch(object $event): object
            {
                $this->events[] = $event::class;

                return $event;
            }

            /**
             * @return list<class-string>
             */
            public function dispatched(): array
            {
                return $this->events;
            }
        };
        $inner = new Registry($dispatcher);

        Server::builder()
            ->setRegistry(new GuardedRegistry($inner, [$this->guard(true)]))
            ->addTool(static fn (): string => 'a', 'alpha')
            ->build();

        $this->assertTrue($inner->hasTool('alpha'));
        $this->assertSame([], $dispatcher->dispatched());

        $inner->unregisterTool('alpha');
        $this->assertSame([ToolListChangedEvent::class], $dispatcher->dispatched());
    }

    public function testFiltersAListWithoutLimit()
    {
        $inner = new Registry();
        $inner->registerTool(new Tool('alpha', null, ['type' => 'object'], null, null), static fn (): string => 'a');

        $this->assertCount(1, (new GuardedRegistry($inner, []))->getTools());
        $this->assertCount(0, (new GuardedRegistry($inner, [$this->guard(true), $this->guard(false)]))->getTools());
    }

    public function testRejectsACursorPastTheFilteredList()
    {
        $inner = new Registry();
        $inner->registerTool(new Tool('alpha', null, ['type' => 'object'], null, null), static fn (): string => 'a');

        $this->expectException(InvalidCursorException::class);

        (new GuardedRegistry($inner, [$this->guard(true)]))->getTools(10, base64_encode('5'));
    }

    private function guard(bool $granted): McpGuardInterface
    {
        return new class($granted) implements McpGuardInterface {
            public function __construct(private readonly bool $granted)
            {
            }

            public function isGranted(Tool|Prompt|ResourceDefinition|ResourceTemplate $element): bool
            {
                return $this->granted;
            }
        };
    }
}
