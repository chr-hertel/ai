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

use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('blog')]
final class TwigComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $message = null;

    public function __construct(
        private readonly Chat $chat,
    ) {
    }

    /**
     * @return MessageInterface[]
     */
    public function getMessages(): array
    {
        return $this->chat->loadMessages()->withoutSystemMessage()->withoutToolMessages()->getMessages();
    }

    #[LiveAction]
    public function submit(): void
    {
        if (null === $this->message || '' === trim($this->message)) {
            return;
        }

        $this->chat->submitMessage($this->message);

        $this->message = null;
    }

    #[LiveAction]
    public function rate(#[LiveArg] string $messageId, #[LiveArg] bool $positive): void
    {
        $this->chat->rate($messageId, $positive);
    }

    public function isRatable(MessageInterface $message): bool
    {
        return $this->chat->isRatable($message->getId()->toRfc4122());
    }

    public function getRating(MessageInterface $message): ?bool
    {
        return $this->chat->getRating($message->getId()->toRfc4122());
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->chat->reset();
    }
}
