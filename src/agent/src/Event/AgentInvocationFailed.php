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

/**
 * Dispatched when an agent invocation fails, right before the exception is rethrown to the consumer of the execution.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AgentInvocationFailed
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly AgentRequest $request,
        private readonly \Throwable $exception,
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

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}
