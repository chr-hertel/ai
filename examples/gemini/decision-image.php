<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Message\Content\Image;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('GEMINI_API_KEY'), http_client());
$result = $platform->invoke('gemini-decision-1', new Evaluation(
    'A photograph to classify.',
    ['outdoors' => new NoulQuestion('Was this photograph taken outdoors?')],
    images: [Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg')],
));

$answer = $result->asObject(Answers::class)->getNoul('outdoors');
echo sprintf('Outdoors: %s (probability: %.2f)', $answer->isTrue() ? 'yes' : 'no', $answer->getProbability()).\PHP_EOL;
