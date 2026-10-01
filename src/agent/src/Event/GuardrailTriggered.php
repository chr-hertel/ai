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

use Symfony\AI\Agent\Context\RunContext;

/**
 * Dispatched by a guardrail whenever it intervenes, so tracing and feedback can record what it did.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class GuardrailTriggered
{
    public const ACTION_DENIED = 'denied';
    public const ACTION_REDACTED = 'redacted';
    public const ACTION_STOPPED = 'stopped';

    /**
     * @param string                     $guardrail name of the guardrail, e.g. "refund_policy" or "token_budget"
     * @param string                     $action    one of the ACTION_* constants, or a custom action
     * @param array<string, scalar|null> $details
     */
    public function __construct(
        private readonly string $guardrail,
        private readonly string $action,
        private readonly ?string $reason = null,
        private readonly ?RunContext $runContext = null,
        private readonly array $details = [],
    ) {
    }

    public function getGuardrail(): string
    {
        return $this->guardrail;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getRunContext(): ?RunContext
    {
        return $this->runContext;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
