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
use Symfony\AI\Agent\Bridge\Filesystem\Filesystem;
use Symfony\AI\Agent\Execution\ToolCallResolution;
use Symfony\AI\Agent\Execution\ToolCallResolutions;
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsRequested;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$workspace = sys_get_temp_dir().'/symfony-ai-suspension';
$filesystem = new SymfonyFilesystem();
$filesystem->dumpFile($workspace.'/notes.txt', 'Some notes.');
$filesystem->dumpFile($workspace.'/todo.txt', 'Buy milk.');

// the filesystem tools carry no effect hints, so the read-only ones are listed explicitly
$readOnlyTools = ['filesystem_list', 'filesystem_read', 'filesystem_exists', 'filesystem_info'];

$eventDispatcher = new EventDispatcher();
$eventDispatcher->addListener(ToolCallsRequested::class, static function (ToolCallsRequested $event) use ($readOnlyTools): void {
    foreach ($event->getToolCalls() as $toolCall) {
        if (true === $event->getDefinition($toolCall)?->isReadOnly() || in_array($toolCall->getName(), $readOnlyTools, true)) {
            continue;
        }

        $event->suspend($toolCall, 'Changes the workspace.');
    }
});

$toolbox = new Toolbox([new Filesystem($filesystem, $workspace, allowDelete: true)], logger: logger(), eventDispatcher: $eventDispatcher);
$agent = new Agent($platform, 'gpt-4o-mini', toolbox: $toolbox, eventDispatcher: $eventDispatcher, messageStore: new InMemoryMessageStore());

$messages = new MessageBag(Message::ofUser('List the files in the workspace, then delete notes.txt.'));
$execution = $agent->call($messages);
$execution->getResult();

while ($execution->isSuspended()) {
    $resolutions = [];
    foreach ($execution->getPendingToolCalls() as $toolCall) {
        output()->write(sprintf('Allow tool "%s" with %s? [y/N] ', $toolCall->getName(), json_encode($toolCall->getArguments())));

        $resolutions[$toolCall->getId()] = 'y' === strtolower(trim(fgets(\STDIN)))
            ? ToolCallResolution::execute()
            : ToolCallResolution::answer('The user rejected this tool call.');
    }

    // in a web application this is a later request, only the id of the stored conversation travels
    $execution = $agent->call(new ToolCallResolutions((new MessageBag())->withId($messages->getId()), $resolutions));
    $execution->getResult();
}

echo $execution->getContent().\PHP_EOL;
