<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\Messenger;

use Symfony\AI\Feedback\Feedback;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RecordFeedback
{
    public function __construct(
        private readonly Feedback $feedback,
    ) {
    }

    public function getFeedback(): Feedback
    {
        return $this->feedback;
    }
}
