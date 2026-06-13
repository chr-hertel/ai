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
 * The data type a model consumes (input) or produces (output).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum Modality: string
{
    use Comparable;

    case TEXT = 'text';
    case IMAGE = 'image';
    case AUDIO = 'audio';
    case VIDEO = 'video';
    case PDF = 'pdf';
}
