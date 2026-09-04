<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Speech\SpeechConfiguration;
use Symfony\AI\Agent\SpeechAgent;
use Symfony\AI\Platform\Bridge\ElevenLabs\Factory as ElevenLabsFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;

require_once dirname(__DIR__).'/bootstrap.php';

$openAiPlatform = OpenAiFactory::createPlatform(env('OPENAI_API_KEY'), httpClient: http_client());
$agent = new Agent($openAiPlatform, 'gpt-4o-mini');

$elevenLabsPlatform = ElevenLabsFactory::createPlatform(
    apiKey: env('ELEVEN_LABS_API_KEY'),
    httpClient: http_client(),
);

$speechAgent = new SpeechAgent($agent, new SpeechConfiguration(
    ttsModel: 'eleven_multilingual_v2',
    ttsOptions: [
        'voice' => 'pqHfZKP75CvOlQylNhV4', // Bill
    ],
    ttsStream: true, // the audio arrives chunk by chunk instead of one buffered file
), textToSpeechPlatform: $elevenLabsPlatform);

$execution = $speechAgent->call(new MessageBag(
    Message::ofUser('Tell me a long story about the history of the Symfony framework.'),
));

$audio = '';
$chunks = 0;

// iterating the speech agent reports the steps of the agent it wraps and its own speech synthesis
foreach ($execution as $update) {
    if (!$update instanceof Progress) {
        continue;
    }

    if ('speech_synthesis' === $update->getStage()) {
        echo \PHP_EOL.'Answer: '.$update->getPayload().\PHP_EOL.\PHP_EOL;

        continue;
    }

    if (!$update->getPayload() instanceof BinaryDelta) {
        echo '>> '.$update->getMessage().\PHP_EOL;

        continue;
    }

    $audio .= $update->getPayload()->getData();

    // simulate a barge-in: the user starts speaking again, so the synthesis is cut off after 5 chunks
    if (5 === ++$chunks) {
        $execution->cancel();
    }
}

file_put_contents('/tmp/speech-partial.mp3', $audio);

output()->writeln(sprintf('>> Stopped after <comment>%d</comment> audio chunks.', $chunks));
output()->writeln('Partial audio saved to <comment>/tmp/speech-partial.mp3</comment>');
