<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Http;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\RecordedToolCall;
use Symfony\AI\Eval\RunRecorder;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Exposes an agent over HTTP for external eval and red-teaming tools like promptfoo or garak, in development only.
 *
 * Takes {"prompt": "..."} or {"messages": [{"role": "user", "content": "..."}]} and answers with {"output": "..."}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TargetController
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly RunRecorder $recorder = new RunRecorder(),
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $messages = $this->messages($request->toArray());
        } catch (JsonException|InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $run = $this->recorder->run($this->agent, $messages);
        if (null !== $error = $run->getError()) {
            throw $error;
        }

        $usage = $run->getTokenUsage();

        return new JsonResponse([
            'output' => $run->getAnswer(),
            'run_id' => $run->getRunId(),
            'token_usage' => null === $usage ? null : [
                'prompt' => $usage->getPromptTokens(),
                'completion' => $usage->getCompletionTokens(),
                'total' => $usage->getTotalTokens(),
            ],
            'tool_calls' => array_map(static fn (RecordedToolCall $call): array => ['name' => $call->getName(), 'arguments' => $call->getArguments()], $run->getToolCalls()),
            'duration' => $run->getDuration(),
        ]);
    }

    /**
     * @param array<mixed> $payload
     */
    private function messages(array $payload): MessageBag
    {
        if (isset($payload['prompt']) && \is_string($payload['prompt']) && '' !== $payload['prompt']) {
            return new MessageBag(Message::ofUser($payload['prompt']));
        }

        if (!isset($payload['messages']) || !\is_array($payload['messages']) || [] === $payload['messages']) {
            throw new InvalidArgumentException('The request needs a "prompt" string or a "messages" list.');
        }

        $messages = new MessageBag();
        foreach ($payload['messages'] as $index => $message) {
            $role = \is_array($message) ? ($message['role'] ?? null) : null;
            $content = \is_array($message) ? ($message['content'] ?? null) : null;

            if (!\is_string($content)) {
                throw new InvalidArgumentException(\sprintf('The message %s needs a "content" string.', $index));
            }

            if (!\in_array($role, ['system', 'user', 'assistant'], true)) {
                throw new InvalidArgumentException(\sprintf('The message %s needs a "role" of "system", "user" or "assistant".', $index));
            }

            $messages = $messages->with(match ($role) {
                'system' => Message::forSystem($content),
                'user' => Message::ofUser($content),
                'assistant' => Message::ofAssistant($content),
            });
        }

        return $messages;
    }
}
