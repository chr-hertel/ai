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
use Symfony\AI\Platform\Bridge\OpenAi\Decision\DecisionModelCatalog;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Content\Image;

require_once dirname(__DIR__).'/bootstrap.php';

// GPT-6 Luna also answers on the Responses API, the decision model catalog makes it a decision model
$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client(), modelCatalog: new DecisionModelCatalog());
$result = $platform->invoke('gpt-6-luna', new Evaluation(
    'A photograph to classify.',
    ['outdoors' => new NoulQuestion('Was this photograph taken outdoors?')],
    images: [Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg')],
));

$answer = $result->asObject(Answers::class)->getNoul('outdoors');
echo sprintf('Outdoors: %s (probability: %.2f)', $answer->isTrue() ? 'yes' : 'no', $answer->getProbability()).\PHP_EOL;
