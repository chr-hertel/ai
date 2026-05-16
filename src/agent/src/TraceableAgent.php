<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type AgentData array{
 *     input: string|MessageBag|UserMessage,
 *     context: Context,
 *     options: array<string, mixed>,
 *     mode: 'call'|'run',
 *     called_at: \DateTimeImmutable,
 * }
 */
final class TraceableAgent implements AgentInterface, ResetInterface
{
    /**
     * @var AgentData[]
     */
    private array $calls = [];

    public function __construct(
        private readonly AgentInterface $agent,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function call(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): ResultInterface
    {
        $this->calls[] = [
            'input' => $input,
            'context' => $context,
            'options' => $options,
            'mode' => 'call',
            'called_at' => $this->clock->now(),
        ];

        return $this->agent->call($input, $context, $options);
    }

    public function run(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): Execution
    {
        $this->calls[] = [
            'input' => $input,
            'context' => $context,
            'options' => $options,
            'mode' => 'run',
            'called_at' => $this->clock->now(),
        ];

        return $this->agent->run($input, $context, $options);
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    /**
     * @return AgentData[]
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
