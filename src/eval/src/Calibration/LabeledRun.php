<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Calibration;

use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\RecordedRun;

/**
 * A run a human labeled with the same criteria the judge uses.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LabeledRun
{
    public function __construct(
        private readonly EvalCase $case,
        private readonly RecordedRun $run,
        private readonly bool $humanPassed,
    ) {
    }

    public function getCase(): EvalCase
    {
        return $this->case;
    }

    public function getRun(): RecordedRun
    {
        return $this->run;
    }

    public function isHumanPassed(): bool
    {
        return $this->humanPassed;
    }
}
