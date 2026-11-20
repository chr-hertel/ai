<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum Signal: string
{
    // explicit, given by the user
    case Thumbs = 'thumbs';
    case Rating = 'rating';
    case Correction = 'correction';

    // implicit, derived from behavior
    case Regenerated = 'regenerated';
    case Rephrased = 'rephrased';
    case Abandoned = 'abandoned';
    case Escalated = 'escalated';
    case ToolError = 'tool_error';
    case Guardrail = 'guardrail';
    case TaskCompleted = 'task_completed';

    // automated
    case EvaluatorScore = 'evaluator_score';

    public function isExplicit(): bool
    {
        return \in_array($this, [self::Thumbs, self::Rating, self::Correction], true);
    }
}
