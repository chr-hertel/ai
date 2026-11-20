<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Simulation;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Lets an agent playing a user with a persona and a goal talk to the agent under test, to find failures that only
 * show over several turns: forgotten context, loops, or giving up too early.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConversationSimulator
{
    public const GOAL_REACHED = 'GOAL_REACHED';

    public function __construct(
        private readonly AgentInterface $userSimulator,
    ) {
    }

    public function simulate(AgentInterface $agent, Scenario $scenario, string $opening): SimulatedConversation
    {
        $conversation = new MessageBag(Message::ofUser($opening));
        $instruction = Message::forSystem(\sprintf(
            "%s\nYour goal: %s\nAnswer with your next message to the assistant only. Once the goal is reached, answer with %s.",
            $scenario->getPersona(),
            $scenario->getGoal(),
            self::GOAL_REACHED,
        ));

        for ($turn = 1; $turn <= $scenario->getMaxTurns(); ++$turn) {
            $answer = (string) $agent->call($conversation)->getContent();
            $conversation = $conversation->with(Message::ofAssistant($answer));

            $reached = $scenario->isGoalReached($answer);
            if (true === $reached) {
                return new SimulatedConversation($conversation, $turn, true);
            }

            $next = trim((string) $this->userSimulator->call($this->flip($conversation)->withSystemMessage($instruction))->getContent());
            if (null === $reached && self::GOAL_REACHED === $next) {
                return new SimulatedConversation($conversation, $turn, true);
            }

            $conversation = $conversation->with(Message::ofUser($next));
        }

        return new SimulatedConversation($conversation, $scenario->getMaxTurns(), false);
    }

    /**
     * The simulated user sees the assistant's messages as the messages of its counterpart.
     */
    private function flip(MessageBag $conversation): MessageBag
    {
        $flipped = [];
        foreach ($conversation->getMessages() as $message) {
            $text = $message->getContent();
            $text = \is_string($text) ? $text : (method_exists($message, 'asText') ? (string) $message->asText() : '');

            $flipped[] = 'assistant' === $message->getRole()->value ? Message::ofUser($text) : Message::ofAssistant($text);
        }

        return new MessageBag(...$flipped);
    }
}
