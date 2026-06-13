<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

/**
 * Thrown when no registered model satisfies the given requirements.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class NoMatchingModelException extends RuntimeException
{
}
