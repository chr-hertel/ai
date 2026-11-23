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
enum Source: string
{
    case User = 'user';
    case Application = 'application';
    case Evaluator = 'evaluator';
    case Annotator = 'annotator';
}
