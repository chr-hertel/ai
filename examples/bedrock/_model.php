<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Bedrock\BedrockClient;
use Symfony\AI\Platform\Bridge\Bedrock\Command\ModelListCommand;
use Symfony\Component\Console\Application;

require_once dirname(__DIR__).'/bootstrap.php';

require_env('AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION');

$bedrockClient = new BedrockClient();

$app = new Application('Amazon Bedrock Model Commands');
$app->addCommands([
    new ModelListCommand($bedrockClient),
]);

$app->run();
