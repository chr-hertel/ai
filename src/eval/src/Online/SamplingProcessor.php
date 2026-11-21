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

use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\RecordedRun;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends a sample of the production runs to the online evaluators, without delaying the answer.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SamplingProcessor implements OutputProcessorInterface
{
    /**
     * @param float                $sampleRate   share of the runs to evaluate, between 0.0 and 1.0
     * @param array<string, mixed> $expectations
     * @param \Closure(): float    $random       returns a number from 0.0 (inclusive) to 1.0 (exclusive)
     */
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly float $sampleRate,
        private readonly array $expectations = [],
        private readonly ?\Closure $random = null,
        private readonly ?string $agent = null,
    ) {
        if ($sampleRate < 0.0 || $sampleRate > 1.0) {
            throw new InvalidArgumentException(\sprintf('The sample rate must be between 0.0 and 1.0, got %s.', $sampleRate));
        }
    }

    public function processOutput(Output $output): void
    {
        if (($this->random ?? static fn (): float => mt_rand() / (mt_getrandmax() + 1))() >= $this->sampleRate) {
            return;
        }

        $this->bus->dispatch(new EvaluateRun($output->getRunContext()->getRunId(), RecordedRun::fromOutput($output), $this->expectations, $this->agent));
    }
}
