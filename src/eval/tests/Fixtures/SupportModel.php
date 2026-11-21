<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Fixtures;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * A scripted support model: it only opens refunds before confirming them when its instruction tells it so.
 */
final class SupportModel
{
    /**
     * @param array{messages: list<array{role: string, content?: string|null, tool_calls?: list<array{id: string, function: array{name: string}}>, tool_call_id?: string}>} $payload
     */
    public function __invoke(Model $model, array $payload): ResultInterface
    {
        $instruction = '';
        $question = '';
        $toolNames = [];
        $toolResults = [];
        foreach ($payload['messages'] as $message) {
            match ($message['role']) {
                'system' => $instruction .= $message['content'] ?? '',
                'user' => $question = $message['content'] ?? '',
                default => null,
            };

            foreach ($message['tool_calls'] ?? [] as $toolCall) {
                $toolNames[$toolCall['id']] = $toolCall['function']['name'];
            }

            if ('tool' === $message['role']) {
                $toolResults[$toolNames[$message['tool_call_id'] ?? ''] ?? ''] = (string) ($message['content'] ?? '');
            }
        }

        $result = match (true) {
            str_contains($question, 'person') => new TextResult('I will connect you with a human agent right away.'),
            1 === preg_match('/SO-\d+/', $question, $match) && !isset($toolResults['order_lookup']) => new ToolCallResult([new ToolCall('call-lookup', 'order_lookup', ['orderNumber' => $match[0]])]),
            str_contains($question, 'money back') && str_contains($instruction, 'ticket id') && !isset($toolResults['open_refund']) => new ToolCallResult([new ToolCall('call-refund', 'open_refund', ['orderNumber' => $this->orderNumber($question), 'reason' => 'too small'])]),
            isset($toolResults['open_refund']) => new TextResult(\sprintf('I opened the refund, your ticket is %s.', $toolResults['open_refund'])),
            str_contains($question, 'money back') => new TextResult('Your refund has been processed.'),
            default => new TextResult(\sprintf('Your order is %s.', $toolResults['order_lookup'] ?? 'unknown')),
        };

        $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 100, completionTokens: 20, totalTokens: 120));

        return $result;
    }

    private function orderNumber(string $question): string
    {
        preg_match('/SO-\d+/', $question, $match);

        return $match[0] ?? '';
    }
}
