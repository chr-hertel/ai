<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Blog;

use App\Blog\Chat;
use App\Conversation\ConversationIds;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\AI\Feedback\InMemoryRecorder;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class ChatTest extends TestCase
{
    /**
     * @var list<list<string>>
     */
    private array $requests = [];

    private InMemoryMessageStore $messageStore;

    private InMemoryRecorder $feedbackRecorder;

    public function testLoadMessagesStartsWithAnEmptyConversation()
    {
        $chat = $this->createChat();

        $this->assertCount(0, $chat->loadMessages());
    }

    public function testSubmitMessageStoresQuestionAndAnswer()
    {
        $chat = $this->createChat();

        $chat->submitMessage('What is Symfony?');

        $messages = $chat->loadMessages()->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        $this->assertSame('What is Symfony?', $messages[0]->asText());
        $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
        $this->assertSame('Answer 1', $messages[1]->asText());
    }

    public function testAgentContinuesTheConversation()
    {
        $chat = $this->createChat();

        $chat->submitMessage('What is Symfony?');
        $chat->submitMessage('Tell me more');

        $this->assertSame([
            ['What is Symfony?'],
            ['What is Symfony?', 'Answer 1', 'Tell me more'],
        ], $this->requests);
        $this->assertCount(4, $chat->loadMessages());
    }

    public function testResetStartsANewConversation()
    {
        $chat = $this->createChat();
        $chat->submitMessage('What is Symfony?');
        $previousId = $chat->loadMessages()->getId();

        $chat->reset();
        $chat->submitMessage('Tell me more');

        $this->assertSame([['What is Symfony?'], ['Tell me more']], $this->requests);
        $this->assertCount(2, $chat->loadMessages());
        $this->assertCount(0, $this->messageStore->load($previousId));
    }

    public function testRatingAnAnswerRecordsFeedbackForItsRun()
    {
        $chat = $this->createChat();
        $chat->submitMessage('What is Symfony?');
        $answerId = $chat->loadMessages()->getMessages()[1]->getId()->toRfc4122();

        $this->assertTrue($chat->isRatable($answerId));
        $this->assertNull($chat->getRating($answerId));

        $chat->rate($answerId, false);
        $chat->rate($answerId, true);

        $this->assertFalse($chat->getRating($answerId));
        $feedback = $this->feedbackRecorder->all();
        $this->assertCount(1, $feedback, 'An answer is rated only once');
        $this->assertSame(Signal::Thumbs, $feedback[0]->getSignal());
        $this->assertFalse($feedback[0]->getValue());
        $this->assertSame($answerId, $feedback[0]->getMessageId());
        $this->assertNotSame('', $feedback[0]->getRunId());
    }

    public function testUnknownMessagesAreNotRated()
    {
        $chat = $this->createChat();

        $chat->rate('01a1f6a8-8ef8-7e59-9838-dd86e708e94e', true);

        $this->assertFalse($chat->isRatable('01a1f6a8-8ef8-7e59-9838-dd86e708e94e'));
        $this->assertCount(0, $this->feedbackRecorder->all());
    }

    private function createChat(): Chat
    {
        $platform = MockPlatformFactory::createPlatform(function (Model $model, array $input): TextResult {
            $this->requests[] = array_column($input['messages'], 'content');

            return new TextResult(\sprintf('Answer %d', \count($this->requests)));
        });
        $this->messageStore = new InMemoryMessageStore();

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->feedbackRecorder = new InMemoryRecorder();

        return new Chat(new ConversationIds($requestStack), $this->messageStore, new Agent($platform, 'gpt-4.1', messageStore: $this->messageStore), $this->feedbackRecorder, $requestStack);
    }
}
