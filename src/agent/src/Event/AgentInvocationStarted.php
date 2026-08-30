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
 * Dispatched before the context processor chain runs. A listener may
 * short-circuit the execution by providing a result.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AgentInvocationStarted
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

    public function setResult(ResultInterface $result): void
    {
        $this->result = $result;
    }

    public function hasResult(): bool
    {
        return null !== $this->result;
    }

    public function getResult(): ?ResultInterface
    {
        return $this->result;
    }
}
