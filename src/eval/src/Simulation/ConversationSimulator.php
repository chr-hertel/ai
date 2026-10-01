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
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\Instruction;
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
        $instruction = new Instruction(\sprintf(
            "%s\nYour goal: %s\nAnswer with your next message to the assistant only. Once the goal is reached, answer with %s.",
            $scenario->getPersona(),
            $scenario->getGoal(),
            self::GOAL_REACHED,
        ));

        for ($turn = 1; $turn <= $scenario->getMaxTurns(); ++$turn) {
            $answer = (string) $agent->call(clone $conversation)->getContent();
            $conversation->add(Message::ofAssistant($answer));

            $reached = $scenario->isGoalReached($answer);
            if (true === $reached) {
                return new SimulatedConversation($conversation, $turn, true);
            }

            $next = trim((string) $this->userSimulator->call($this->flip($conversation), new Context($instruction))->getContent());
            if (null === $reached && str_contains($next, self::GOAL_REACHED)) {
                return new SimulatedConversation($conversation, $turn, true);
            }

            $conversation->add(Message::ofUser($next));
        }

        return new SimulatedConversation($conversation, $scenario->getMaxTurns(), false);
    }

    /**
     * The simulated user sees the assistant's messages as the messages of its counterpart.
     */
    private function flip(MessageBag $conversation): MessageBag
    {
        $flipped = new MessageBag();
        foreach ($conversation->getMessages() as $message) {
            $text = $message->getContent();
            $text = \is_string($text) ? $text : (method_exists($message, 'asText') ? (string) $message->asText() : '');

            $flipped->add('assistant' === $message->getRole()->value ? Message::ofUser($text) : Message::ofAssistant($text));
        }

        return $flipped;
    }
}
