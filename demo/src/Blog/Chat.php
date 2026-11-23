<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Blog;

use App\Conversation\ConversationIds;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\FeedbackRecorderInterface;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final class Chat
{
    private const CHAT = 'blog';
    private const RUNS = 'blog-runs';

    public function __construct(
        private readonly ConversationIds $conversationIds,
        #[Autowire(service: 'ai.message_store.session.conversations')]
        private readonly MessageStoreInterface $messageStore,
        #[Autowire(service: 'ai.agent.blog')]
        private readonly AgentInterface $agent,
        private readonly FeedbackRecorderInterface $feedbackRecorder,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function loadMessages(): MessageBag
    {
        return $this->messageStore->load($this->conversationIds->get(self::CHAT));
    }

    public function submitMessage(string $message): void
    {
        $messages = MessageBag::forId($this->conversationIds->get(self::CHAT), $message);

        $metadata = $this->agent->call($messages)->getResult()->getMetadata();

        $answers = array_filter($this->loadMessages()->getMessages(), static fn ($message): bool => $message instanceof AssistantMessage);
        $answer = end($answers);
        if (false === $answer) {
            return;
        }

        // the stored answer doesn't carry the run, so the rating needs it from the session
        $runs = $this->runs();
        $runs[$answer->getId()->toRfc4122()] = ['run_id' => $metadata->get('run_id'), 'trace_id' => $metadata->get('trace_id'), 'rating' => null];
        $this->requestStack->getSession()->set(self::RUNS, $runs);
    }

    public function rate(string $messageId, bool $positive): void
    {
        $runs = $this->runs();
        if (!isset($runs[$messageId]) || null !== $runs[$messageId]['rating']) {
            return;
        }

        $this->feedbackRecorder->record(new Feedback(
            $runs[$messageId]['run_id'],
            Signal::Thumbs,
            $positive,
            messageId: $messageId,
            traceId: $runs[$messageId]['trace_id'],
            id: $messageId,
        ));

        $runs[$messageId]['rating'] = $positive;
        $this->requestStack->getSession()->set(self::RUNS, $runs);
    }

    public function getRating(string $messageId): ?bool
    {
        return $this->runs()[$messageId]['rating'] ?? null;
    }

    public function isRatable(string $messageId): bool
    {
        return isset($this->runs()[$messageId]);
    }

    public function reset(): void
    {
        $this->messageStore->delete($this->conversationIds->get(self::CHAT));
        $this->conversationIds->reset(self::CHAT);
        $this->requestStack->getSession()->remove(self::RUNS);
    }

    /**
     * @return array<string, array{run_id: string, trace_id: string|null, rating: bool|null}>
     */
    private function runs(): array
    {
        return $this->requestStack->getSession()->get(self::RUNS, []);
    }
}
