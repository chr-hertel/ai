<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context\Processor;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Message;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Injects the agent {@see Instruction}(s) from the context as the system message.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InstructionProcessor implements ContextProcessorInterface
{
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public static function supportedTypes(): array
    {
        return [Instruction::class];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
        $messages = $request->getMessageBag();

        if (null !== $messages->getSystemMessage()) {
            $this->logger->debug('Skipping instruction injection since MessageBag already contains a system message.');

            return;
        }

        $instructions = $request->getContext()->all(Instruction::class);
        if ([] === $instructions) {
            return;
        }

        $rendered = [];
        foreach ($instructions as $instruction) {
            \assert($instruction instanceof Instruction);
            $rendered[] = $this->render($instruction->getContent());
        }

        $request->setMessageBag($messages->withSystemMessage(Message::forSystem(implode(\PHP_EOL.\PHP_EOL, $rendered))));
    }

    private function render(string|\Stringable|TranslatableInterface|File $content): string
    {
        if ($content instanceof File) {
            return $content->asBinary();
        }

        if ($content instanceof TranslatableInterface) {
            if (null === $this->translator) {
                throw new RuntimeException('Translatable instruction is not supported when no translator is provided.');
            }

            return $content->trans($this->translator);
        }

        return (string) $content;
    }
}
