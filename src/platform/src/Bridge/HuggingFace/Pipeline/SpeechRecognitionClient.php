<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Pipeline;

use Symfony\AI\Platform\Bridge\HuggingFace\SpeechRecognition;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SpeechRecognitionClient extends AbstractPipelineClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof SpeechRecognition;
    }

    public function convert(RawResultInterface $result, array $options = []): TextResult
    {
        $data = $this->getResponseData($result);

        if (!isset($data['text'])) {
            throw new RuntimeException('Response does not contain transcription text.');
        }

        return new TextResult($data['text']);
    }
}
