<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Prompt;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Prompt\InMemoryPromptRegistry;
use Symfony\AI\Agent\Prompt\PercentageRolloutSelector;
use Symfony\AI\Agent\Prompt\PromptInputProcessor;
use Symfony\AI\Agent\Prompt\YamlPromptRegistry;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Test\MockPlatformFactory;

final class PromptInputProcessorTest extends TestCase
{
    public function testResolvesTheSystemPromptPerRun()
    {
        $processor = new PromptInputProcessor(
            new YamlPromptRegistry([__DIR__.'/Fixtures']),
            'support/system',
            version: '2026-08-v2',
            variables: ['shop_name' => 'ACME'],
            selector: new PercentageRolloutSelector('support/system', '2026-09-v3', '2026-08-v2', 100),
        );

        $payloads = [];
        $platform = MockPlatformFactory::createPlatform(static function ($model, array|string $payload) use (&$payloads): string {
            $payloads[] = json_encode($payload);

            return 'Hello';
        });

        $agent = new Agent($platform, 'gpt-4o', [$processor]);
        $runContext = $agent->call('Hi', ['run_context' => new RunContext('run-1')])->getResult()->getMetadata()->get('run_context');

        $this->assertInstanceOf(RunContext::class, $runContext);
        $this->assertSame('run-1', $runContext->getRunId());
        $this->assertSame('support/system@2026-09-v3', (string) $runContext->getPrompt());
        $this->assertCount(1, $payloads);
        $this->assertStringContainsString('Never promise a refund', (string) $payloads[0]);
    }

    public function testReplacesAnExistingSystemMessage()
    {
        $registry = new InMemoryPromptRegistry(['greeting' => ['v1' => 'Greet {name}.']]);
        $input = new Input('gpt-4o', new MessageBag(Message::forSystem('Old prompt.'), Message::ofUser('Hi')));

        (new PromptInputProcessor($registry, 'greeting', variables: ['name' => 'Ada']))->processInput($input);

        $this->assertSame('Greet Ada.', $input->getMessageBag()->getSystemMessage()?->getContent());
        $this->assertCount(2, $input->getMessageBag());
        $this->assertSame('greeting@v1', (string) $input->getRunContext()->getPrompt());
    }
}
