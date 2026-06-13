<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Whisper;

use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Model;

/**
 * Handles the OpenAI audio transcription endpoint (the Whisper default task).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TranscriptionEndpointHandler extends AbstractEndpointHandler
{
    public function supports(Model $model, ?string $task = null): bool
    {
        return $model instanceof Whisper && (null === $task || Task::TRANSCRIPTION === $task);
    }

    protected function endpoint(): string
    {
        return 'transcriptions';
    }
}
