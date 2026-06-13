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
 * The operation a model performs, decoupled from the modalities it consumes or produces.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum Task: string
{
    use Comparable;

    case TEXT_GENERATION = 'text-generation';
    case IMAGE_GENERATION = 'image-generation';
    case VIDEO_GENERATION = 'video-generation';
    case SPEECH_SYNTHESIS = 'speech-synthesis';
    case TRANSCRIPTION = 'transcription';
    case EMBEDDING = 'embedding';
    case RERANKING = 'reranking';
}
