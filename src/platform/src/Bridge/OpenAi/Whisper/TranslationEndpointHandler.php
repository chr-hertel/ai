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
 * Handles the OpenAI audio translation endpoint (Whisper translation task).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TranslationEndpointHandler extends AbstractEndpointHandler
{
    public function supports(Model $model, ?string $task = null): bool
    {
        return $model instanceof Whisper && Task::TRANSLATION === $task;
    }

    protected function endpoint(): string
    {
        return 'translations';
    }
}
