<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Online;

use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\AgentResult;
use Symfony\AI\Agent\Context\ResultAwareContextProcessorInterface;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Eval\RecordedRun;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends a sample of the production runs to the online evaluators, without delaying the answer.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SamplingProcessor implements ResultAwareContextProcessorInterface
{
    /**
     * @param float                $sampleRate   share of the runs to evaluate, between 0.0 and 1.0
     * @param array<string, mixed> $expectations
     * @param \Closure(): float    $random       returns a number between 0.0 and 1.0
     */
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly float $sampleRate,
        private readonly array $expectations = [],
        private readonly ?\Closure $random = null,
    ) {
    }

    public static function supportedTypes(): array
    {
        return [];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
    }

    public function processResult(AgentResult $result, AgentContext $context): void
    {
        $runContext = $result->getContext()->get(RunContext::class);
        if (null === $runContext || ($this->random ?? static fn (): float => mt_rand() / mt_getrandmax())() >= $this->sampleRate) {
            return;
        }

        $this->bus->dispatch(new EvaluateRun($runContext->getRunId(), RecordedRun::fromAgentResult($result), $this->expectations));
    }
}
