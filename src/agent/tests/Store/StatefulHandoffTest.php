<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Store;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Handoff\Decision;
use Symfony\AI\Agent\Handoff\Handoff;
use Symfony\AI\Agent\Handoff\History;
use Symfony\AI\Agent\Store\InMemoryStore;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;

final class StatefulHandoffTest extends TestCase
{
    public function testTheDelegatingAgentPersistsDelegatedAndOwnTurnsInOrder()
    {
        $store = new InMemoryStore();
        $technical = new Agent(new InMemoryPlatform('Restart the printer.'), 'gpt-4o', name: 'technical');

        $agent = new Agent(
            $this->orchestratorPlatform(['technical', ''], 'You are welcome!'),
            'gpt-4o',
            context: new Context(new Handoff($technical, 'technical problems')),
            store: $store,
        );

        $agent->call('My printer is broken.')->getResult();
        $agent->call('Thanks!')->getResult();

        $messages = $store->load()->getMessages();

        $this->assertCount(4, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        $this->assertSame('My printer is broken.', $messages[0]->asText());
        $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
        $this->assertSame('Restart the printer.', $messages[1]->asText());
        $this->assertSame('technical', $messages[1]->getMetadata()->get('delegated_to'));
        $this->assertInstanceOf(UserMessage::class, $messages[2]);
        $this->assertSame('Thanks!', $messages[2]->asText());
        $this->assertInstanceOf(AssistantMessage::class, $messages[3]);
        $this->assertSame('You are welcome!', $messages[3]->asText());
        $this->assertFalse($messages[3]->getMetadata()->has('delegated_to'));
    }

    public function testATargetWithItsOwnStoreOnlyGetsTheLatestMessageToAvoidDuplicates()
    {
        $seen = [];
        $targetStore = new InMemoryStore();
        $technical = new Agent(
            new InMemoryPlatform(static function (mixed $model, MessageBag $input) use (&$seen): TextResult {
                $seen[] = \count($input);

                return new TextResult('Technical answer.');
            }),
            'gpt-4o',
            name: 'technical',
            store: $targetStore,
        );

        $store = new InMemoryStore();
        $agent = new Agent(
            $this->orchestratorPlatform(['technical', 'technical']),
            'gpt-4o',
            context: new Context(new Handoff($technical, 'technical problems', history: History::lastMessage())),
            store: $store,
        );

        $agent->call('My printer is broken.')->getResult();
        $agent->call('It still does not work.')->getResult();

        $this->assertSame([1, 3], $seen);
        $this->assertCount(4, $store->load());
        $this->assertCount(4, $targetStore->load());
    }

    /**
     * Answers each routing call with the next of the given agent names, and every other call as the orchestrator.
     *
     * @param list<string> $decisions
     */
    private function orchestratorPlatform(array $decisions, string $answer = 'The orchestrator answers.'): InMemoryPlatform
    {
        return new InMemoryPlatform(static function (mixed $model, mixed $input, array $options) use (&$decisions, $answer): ResultInterface {
            if (!isset($options['response_format'])) {
                return new TextResult($answer);
            }

            return new ObjectResult(new Decision(array_shift($decisions) ?? '', 'Because of the topic.'));
        });
    }
}
