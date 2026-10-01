<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Event;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Dispatched right before each platform invocation.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelRequested
{
    private ?ResultInterface $result = null;

    public function __construct(
        private readonly AgentInterface $agent,
        private readonly AgentRequest $request,
    ) {
    }

    public function getAgent(): AgentInterface
    {
        return $this->agent;
    }

    public function getRequest(): AgentRequest
    {
        return $this->request;
    }

    /**
     * Ends the run before the model is invoked, using the given result as the final result.
     *
     * Used by budgets and guardrails that need to stop a runaway tool calling loop gracefully.
     */
    public function stop(ResultInterface $result): void
    {
        $this->result = $result;
    }

    public function isStopped(): bool
    {
        return null !== $this->result;
    }

    public function getResult(): ?ResultInterface
    {
        return $this->result;
    }
}
