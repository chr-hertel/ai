<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Movie;

use App\Conversation\ConversationIds;
use App\Movies\Chat;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\AI\Feedback\InMemoryRecorder;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\MessageBusInterface;

final class ChatTest extends TestCase
{
    private InMemoryMessageStore $messageStore;

    private InMemoryRecorder $feedbackRecorder;

    public function testRatingAnAnswerRecordsFeedbackForItsRun()
    {
        $chat = $this->createChat();
        $answer = $this->answer(Message::ofAssistant('Inception.'), 'run-1', 'trace-1');
        $this->conversationWith($chat, Message::ofUser('Movies by Christopher Nolan'), $answer);
        $answerId = $answer->getId()->toRfc4122();

        $chat->rate($answerId, true);
        $chat->rate($answerId, false);

        $feedback = $this->feedbackRecorder->all();
        $this->assertCount(1, $feedback, 'An answer is rated only once');
        $this->assertSame('run-1', $feedback[0]->getRunId());
        $this->assertSame('trace-1', $feedback[0]->getTraceId());
        $this->assertSame(Signal::Thumbs, $feedback[0]->getSignal());
        $this->assertTrue($feedback[0]->getValue());
        $this->assertSame($answerId, $feedback[0]->getMessageId());
        $this->assertTrue($chat->loadMessages()->getMessages()[1]->getMetadata()->get('rating'));
    }

    public function testAnswersWithoutRunAreNotRated()
    {
        $chat = $this->createChat();
        $apology = Message::ofAssistant('Sorry, I could not search the collection. Please try again.');
        $this->conversationWith($chat, Message::ofUser('Movies by Christopher Nolan'), $apology);

        $chat->rate($apology->getId()->toRfc4122(), false);
        $chat->rate('01a1f6a8-8ef8-7e59-9838-dd86e708e94e', true);

        $this->assertCount(0, $this->feedbackRecorder->all());
        $this->assertNull($chat->loadMessages()->getMessages()[1]->getMetadata()->get('rating'));
    }

    private function answer(AssistantMessage $message, string $runId, string $traceId): AssistantMessage
    {
        $message->getMetadata()->add('run_id', $runId);
        $message->getMetadata()->add('trace_id', $traceId);

        return $message;
    }

    private function conversationWith(Chat $chat, MessageInterface ...$messages): void
    {
        $this->messageStore->save(MessageBag::forId($chat->loadMessages()->getId(), ...$messages));
    }

    private function createChat(): Chat
    {
        $this->messageStore = new InMemoryMessageStore();
        $this->feedbackRecorder = new InMemoryRecorder();

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new Chat($requestStack, new ConversationIds($requestStack), $this->messageStore, $this->createStub(MessageBusInterface::class), $this->feedbackRecorder);
    }
}
