<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\UniversalAi;

use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis;
use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis\Result\ImageAnalysisResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ImageAnalysisClient extends AbstractUniversalAiClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof ImageAnalysis;
    }

    public function convert(RawResultInterface $result, array $options = []): ObjectResult
    {
        $data = $this->getResponseData($result);

        if (!isset($data['output']) || !\is_array($data['output'])) {
            throw new RuntimeException('Response does not contain output.');
        }

        return new ObjectResult(new ImageAnalysisResult(
            $data['output'],
            $data['provider'] ?? null,
            isset($data['cost']) ? (float) $data['cost'] : null,
            $data['original_response'] ?? null,
        ));
    }
}
