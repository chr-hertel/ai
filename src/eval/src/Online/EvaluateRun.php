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

use Symfony\AI\Eval\RecordedRun;

/**
 * Asks a worker to score a production run.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EvaluateRun
{
    /**
     * @param array<string, mixed> $expectations the expectations of the online evaluators, e.g. judge criteria
     */
    public function __construct(
        private readonly string $runId,
        private readonly RecordedRun $run,
        private readonly array $expectations = [],
    ) {
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getRun(): RecordedRun
    {
        return $this->run;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExpectations(): array
    {
        return $this->expectations;
    }
}
