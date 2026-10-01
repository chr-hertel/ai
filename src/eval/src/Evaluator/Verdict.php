<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Evaluator;

/**
 * The structured answer of an LLM judge.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Verdict
{
    /**
     * Short reasoning about how the transcript meets the criteria, written before deciding on the score.
     */
    public string $reasoning = '';

    /**
     * Score between 0.0 (fails the criteria) and 1.0 (fully meets the criteria).
     */
    public float $score = 0.0;
}
