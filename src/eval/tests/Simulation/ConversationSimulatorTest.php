<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Simulation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Eval\Simulation\ConversationSimulator;
use Symfony\AI\Eval\Simulation\Scenario;
use Symfony\AI\Platform\Test\MockPlatformFactory;

final class ConversationSimulatorTest extends TestCase
{
    public function testConversationRunsUntilTheGoalIsReached()
    {
        // the assistant asks for the order number until it got it
        $assistant = new Agent(MockPlatformFactory::createPlatform(static function ($model, array $payload): string {
            $users = array_filter($payload['messages'], static fn (array $message): bool => 'user' === $message['role']);

            return str_contains((string) (end($users)['content'] ?? ''), 'SO-10023') ? 'Your refund ticket is RF-4711.' : 'Which order is it about?';
        }), 'assistant');

        // the impatient customer gives the order number only when asked twice
        $asked = 0;
        $customer = new Agent(MockPlatformFactory::createPlatform(static function () use (&$asked): string {
            return ++$asked >= 2 ? 'Fine, it is SO-10023.' : 'Just refund my jacket!';
        }), 'customer');

        $conversation = (new ConversationSimulator($customer))->simulate(
            $assistant,
            new Scenario('An impatient customer.', 'A refund ticket id is given.', 6, static fn (string $answer): bool => str_contains($answer, 'RF-')),
            'I want a refund.',
        );

        $this->assertTrue($conversation->isGoalReached());
        $this->assertSame(3, $conversation->getTurns());
        $this->assertCount(6, $conversation->getMessages());
    }

    public function testConversationStopsAfterTheMaximumTurns()
    {
        $assistant = new Agent(MockPlatformFactory::createPlatform('Which order is it about?'), 'assistant');
        $customer = new Agent(MockPlatformFactory::createPlatform('No.'), 'customer');

        $conversation = (new ConversationSimulator($customer))->simulate($assistant, new Scenario('A stubborn customer.', 'A refund.', 2), 'Refund!');

        $this->assertFalse($conversation->isGoalReached());
        $this->assertSame(2, $conversation->getTurns());
    }

    public function testSimulatedUserEndsTheConversationWithTheMarkerOnly()
    {
        $assistant = new Agent(MockPlatformFactory::createPlatform('Your ticket is RF-4711.'), 'assistant');
        $customer = new Agent(MockPlatformFactory::createPlatform(" GOAL_REACHED\n"), 'customer');

        $conversation = (new ConversationSimulator($customer))->simulate($assistant, new Scenario('A customer.', 'A refund.', 3), 'Refund!');

        $this->assertTrue($conversation->isGoalReached());
        $this->assertSame(1, $conversation->getTurns());
    }

    public function testMarkerWithinAMessageDoesNotEndTheConversation()
    {
        $assistant = new Agent(MockPlatformFactory::createPlatform('Which order is it about?'), 'assistant');
        $customer = new Agent(MockPlatformFactory::createPlatform('Not GOAL_REACHED yet, which order do you mean?'), 'customer');

        $conversation = (new ConversationSimulator($customer))->simulate($assistant, new Scenario('A customer.', 'A refund.', 2), 'Refund!');

        $this->assertFalse($conversation->isGoalReached());
        $this->assertSame(2, $conversation->getTurns());
    }
}
