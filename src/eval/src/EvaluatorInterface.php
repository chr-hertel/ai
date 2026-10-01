<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface EvaluatorInterface
{
    public function getName(): string;

    /**
     * @return Score|null null if the case does not expect anything this evaluator checks
     */
    public function evaluate(EvalCase $case, RecordedRun $run): ?Score;
}
