<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\UpdateInterface;

/**
 * Handle given to a {@see ContextProcessorInterface} during a run. It exposes
 * the running agent, lets a processor emit progress updates that the runner
 * forwards to the consumer, and lets it hand the run over to another execution.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AgentContext
{
    /**
     * @var list<UpdateInterface>
     */
    private array $updates = [];

    private ?Execution $delegation = null;

    private ?string $delegationTarget = null;

    public function __construct(
        private readonly AgentInterface $agent,
        private readonly ?Cancellation $cancellation = null,
    ) {
    }

    public function getAgent(): AgentInterface
    {
        return $this->agent;
    }

    public function getCancellation(): ?Cancellation
    {
        return $this->cancellation;
    }

    /**
     * Hands the run over to the given execution: the runner skips the remaining processors and the model, forwards
     * the updates of that execution, and completes the run with its result.
     *
     * @param string|null $target names the delegate in the result metadata as `delegated_to`
     */
    public function delegate(Execution $execution, ?string $target = null): void
    {
        if (null !== $this->delegation) {
            throw new LogicException('The run has already been delegated.');
        }

        $this->delegation = $execution;
        $this->delegationTarget = $target;
    }

    public function getDelegation(): ?Execution
    {
        return $this->delegation;
    }

    public function getDelegationTarget(): ?string
    {
        return $this->delegationTarget;
    }

    public function notify(UpdateInterface $update): void
    {
        $this->updates[] = $update;
    }

    public function reportProgress(string $stage, string $message = '', mixed $payload = null): void
    {
        $this->updates[] = new Progress($stage, $message, $payload);
    }

    /**
     * Returns and clears the buffered updates. Called by the runner.
     *
     * @return list<UpdateInterface>
     */
    public function flushUpdates(): array
    {
        $updates = $this->updates;
        $this->updates = [];

        return $updates;
    }
}
