<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use OskarStark\Enum\Trait\Comparable;

/**
 * An orthogonal capability a model may expose, independent of its task and modalities.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum Feature: string
{
    use Comparable;

    case TOOL_CALLING = 'tool-calling';
    case STREAMING = 'streaming';
    case STRUCTURED_OUTPUT = 'structured-output';
    case THINKING = 'thinking';
    case FILL_IN_THE_MIDDLE = 'fill-in-the-middle';
    case MULTIPLE_INPUTS = 'multiple-inputs';
}
