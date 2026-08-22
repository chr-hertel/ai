<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Subway\Exception;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SubwayException extends \RuntimeException
{
    public static function unexpectedPayload(string $tool): self
    {
        return new self(\sprintf('The "%s" tool did not return the expected structured content.', $tool));
    }

    public static function callFailed(string $tool, \Throwable $previous): self
    {
        return new self(\sprintf('Calling the "%s" tool failed: %s', $tool, $previous->getMessage()), previous: $previous);
    }
}
