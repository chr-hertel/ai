<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\MultiPartResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::ofUser('Generate an image of a cartoon-style elephant with a long trunk and large ears.'),
);

// Enable OpenAI's native, server-side image generation tool for this call.
$result = $platform->invoke('gpt-4.1', $messages, [
    'tools' => [
        ['type' => 'image_generation'],
    ],
]);

// The generated image is surfaced as a BinaryResult.
$converted = $result->getResult();
$parts = $converted instanceof MultiPartResult ? $converted->getContent() : [$converted];

foreach ($parts as $part) {
    if ($part instanceof BinaryResult) {
        $file = output_file('openai-image-generation.png');
        $part->asFile($file);
        echo 'Image saved to '.$file.\PHP_EOL;
    }
}
