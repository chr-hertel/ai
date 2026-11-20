<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * The support agent of a web shop, shared by the production and the eval script.
 *
 * The model is scripted, so the whole loop runs without an API key and gives the same result on every run. It
 * behaves like the real one did in production: with the prompt version 2026-08-v2 it promises refunds without
 * opening them, and the cheaper model does not hand over to a human when asked to.
 */

use Symfony\AI\Agent\Event\GuardrailTriggered;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Eval\Evaluator\Verdict;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

const SHOP_NOW = '2026-10-01';

const SUPPORT_PRICES = [
    'claude-sonnet-4-5' => ['input' => 3.0, 'output' => 15.0],
    'claude-haiku-4-5' => ['input' => 1.0, 'output' => 5.0],
    'claude-opus-4-5' => ['input' => 5.0, 'output' => 25.0],
];

#[AsTool('order_lookup', 'Looks up an order by its number and returns status and delivery date.', method: 'lookup')]
#[AsTool('open_refund', 'Opens a refund request. Only use after confirming the order with order_lookup.', method: 'refund')]
final class ShopTools
{
    /**
     * @var array<string, array{status: string, items: list<string>, delivered_at: string|null}>
     */
    private const ORDERS = [
        'SO-10023' => ['status' => 'delivered', 'items' => ['Jacket M'], 'delivered_at' => '2026-09-20'],
        'SO-10024' => ['status' => 'shipped', 'items' => ['Sneakers 42'], 'delivered_at' => null],
        'SO-9001' => ['status' => 'delivered', 'items' => ['Scarf'], 'delivered_at' => '2026-06-02'],
    ];

    /**
     * @param string $orderNumber The order number, e.g. "SO-10023"
     *
     * @return array{status: string, items: list<string>, delivered_at: string|null}
     */
    public function lookup(string $orderNumber): array
    {
        return self::ORDERS[$orderNumber] ?? throw new InvalidArgumentException(sprintf('Order "%s" not found.', $orderNumber));
    }

    /**
     * @param string $orderNumber The order number
     * @param string $reason      Short reason given by the customer
     */
    public function refund(string $orderNumber, string $reason): string
    {
        return sprintf('RF-%d', 4000 + crc32($orderNumber) % 1000);
    }

    public static function deliveredAt(string $orderNumber): ?string
    {
        return self::ORDERS[$orderNumber]['delivered_at'] ?? null;
    }
}

/**
 * Denies refunds outside the 30 days refund window, before the tool runs, and reports it as guardrail.
 */
final class RefundPolicyGuard
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function __invoke(ToolCallRequested $event): void
    {
        if ('open_refund' !== $event->getDefinition()->getName()) {
            return;
        }

        $orderNumber = (string) ($event->getToolCall()->getArguments()['orderNumber'] ?? '');
        $deliveredAt = ShopTools::deliveredAt($orderNumber);

        if (null === $deliveredAt || new DateTimeImmutable($deliveredAt) > new DateTimeImmutable(SHOP_NOW.' -30 days')) {
            return;
        }

        $reason = 'This order is outside the refund window. Offer to escalate to a human instead.';
        $event->deny($reason);

        $this->dispatcher->dispatch(new GuardrailTriggered('refund_policy', GuardrailTriggered::ACTION_DENIED, $reason, $event->getRunContext(), ['order' => $orderNumber]));
    }
}

/**
 * The messages of a request as the mock platform passes them to the scripted models.
 */
final class Conversation
{
    /**
     * @param array{messages: list<array{role: string, content?: mixed, tool_calls?: list<array{id: string, function: array{name: string}}>, tool_call_id?: string}>} $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function count(): int
    {
        return count($this->payload['messages']);
    }

    public function text(string $role): string
    {
        $text = '';
        foreach ($this->payload['messages'] as $message) {
            if ($role === $message['role']) {
                $text = self::asText($message['content'] ?? '');
            }
        }

        return $text;
    }

    /**
     * @return array<string, string> the results of the tool calls by tool name
     */
    public function toolResults(): array
    {
        $names = [];
        $results = [];
        foreach ($this->payload['messages'] as $message) {
            foreach ($message['tool_calls'] ?? [] as $toolCall) {
                $names[$toolCall['id']] = $toolCall['function']['name'];
            }

            if ('tool' === $message['role']) {
                $results[$names[$message['tool_call_id'] ?? ''] ?? ''] = self::asText($message['content'] ?? '');
            }
        }

        return $results;
    }

    private static function asText(mixed $content): string
    {
        if (!is_array($content)) {
            return (string) $content;
        }

        return implode('', array_map(static fn (array $part): string => (string) ($part['text'] ?? ''), $content));
    }
}

/**
 * The scripted support model.
 */
final class SupportModel
{
    /**
     * @param array{messages: list<array<string, mixed>>} $payload
     */
    public function __invoke(Model $model, array $payload): ResultInterface
    {
        $conversation = new Conversation($payload);
        $instruction = $conversation->text('system');
        $question = $conversation->text('user');
        $results = $conversation->toolResults();
        preg_match('/SO-\d+/', $question, $match);
        $orderNumber = $match[0] ?? null;

        $result = match (true) {
            str_contains($question, 'person') && str_contains($model->getName(), 'haiku') => new TextResult('Let me try to solve this for you first. What is your order number?'),
            str_contains($question, 'person') => new TextResult('I am sorry for the trouble. I will connect you with a human agent right away.'),
            // a runaway loop: the model never stops looking up orders
            str_contains($question, 'every order') => new ToolCallResult([new ToolCall('call-'.$conversation->count(), 'order_lookup', ['orderNumber' => 0 === $conversation->count() % 2 ? 'SO-10023' : 'SO-10024'])]),
            null !== $orderNumber && !isset($results['order_lookup']) => new ToolCallResult([new ToolCall('call-lookup', 'order_lookup', ['orderNumber' => $orderNumber])]),
            str_contains($results['order_lookup'] ?? '', 'error occurred') => new TextResult(sprintf('I could not find the order %s, please check the number.', $orderNumber)),
            $this->wantsRefund($question) && str_contains($instruction, 'ticket id') && !isset($results['open_refund']) => new ToolCallResult([new ToolCall('call-refund', 'open_refund', ['orderNumber' => $orderNumber, 'reason' => 'Customer request'])]),
            str_contains($results['open_refund'] ?? '', 'refund window') => new TextResult('I am sorry, this order is outside our refund window. I can connect you with a human agent to look for a solution.'),
            isset($results['open_refund']) => new TextResult(sprintf('I opened the refund for %s, your ticket is %s.', $orderNumber, $results['open_refund'])),
            $this->wantsRefund($question) => new TextResult('No problem, your refund has been processed.'),
            default => new TextResult(sprintf('Your order %s is %s.', $orderNumber, $this->status($results['order_lookup'] ?? ''))),
        };

        $promptTokens = 400 + 120 * $conversation->count();
        $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: $promptTokens, completionTokens: 60, totalTokens: $promptTokens + 60, model: $model->getName().'-20250929'));

        return $result;
    }

    private function wantsRefund(string $question): bool
    {
        return str_contains($question, 'money back') || str_contains($question, 'refund');
    }

    private function status(string $lookup): string
    {
        $data = json_decode($lookup, true);

        return is_array($data) ? (string) $data['status'] : 'unknown';
    }
}

/**
 * The scripted judge, a little too lenient: it accepts any answer mentioning a ticket.
 */
final class JudgeModel
{
    /**
     * @param array{messages: list<array<string, mixed>>} $payload
     */
    public function __invoke(Model $model, array $payload): ResultInterface
    {
        $prompt = (new Conversation($payload))->text('user');
        $answer = substr($prompt, (int) strrpos($prompt, 'Assistant (final answer):'));

        $verdict = new Verdict();
        [$verdict->score, $verdict->reasoning] = match (true) {
            str_contains($prompt, 'ticket id') && str_contains($answer, 'ticket') => [0.9, 'The answer mentions a ticket.'],
            str_contains($prompt, 'ticket id') => [0.1, 'The refund is confirmed without a ticket id.'],
            str_contains($prompt, 'human agent') && str_contains($answer, 'human agent') => [1.0, 'Escalates right away.'],
            default => [0.2, 'Does not meet the criteria.'],
        };

        return new ObjectResult($verdict);
    }
}
