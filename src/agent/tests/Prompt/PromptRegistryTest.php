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
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Event\AgentInvocationStarted;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Prompt\InMemoryPromptRegistry;
use Symfony\AI\Agent\Prompt\PercentageRolloutSelector;
use Symfony\AI\Agent\Prompt\PromptInstructionListener;
use Symfony\AI\Agent\Prompt\YamlPromptRegistry;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class PromptRegistryTest extends TestCase
{
    public function testYamlRegistryLoadsVersionsInOrder()
    {
        $registry = new YamlPromptRegistry([__DIR__.'/Fixtures']);

        $this->assertSame(['2026-08-v2', '2026-09-v3'], $registry->versions('support/system'));
        $this->assertSame('2026-09-v3', $registry->get('support/system')->getReference()->getVersion());
        $this->assertSame("You are the support assistant of ACME.\n", $registry->get('support/system', '2026-08-v2')->render(['shop_name' => 'ACME']));
    }

    public function testUnknownVersionIsRejected()
    {
        $registry = new InMemoryPromptRegistry(['greeting' => ['v1' => 'Hi']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt "greeting" has no version "v2"');

        $registry->get('greeting', 'v2');
    }

    public function testPromptReferenceHashChangesWithTheTemplate()
    {
        $registry = new InMemoryPromptRegistry(['a' => ['v1' => 'One', 'v2' => 'Two']]);

        $this->assertNotSame($registry->get('a', 'v1')->getReference()->getHash(), $registry->get('a', 'v2')->getReference()->getHash());
    }

    public function testRolloutSelectorIsStablePerConversation()
    {
        $selector = new PercentageRolloutSelector('support/system', 'v3', 'v2', 50);

        $versions = [];
        foreach (range(1, 200) as $i) {
            $versions[] = $selector->select('support/system', new RunContext('run-'.$i));
        }

        $this->assertContains('v3', $versions);
        $this->assertContains('v2', $versions);
        $this->assertSame(
            $selector->select('support/system', new RunContext('run-1', attributes: ['conversation' => 'c-1'])),
            $selector->select('support/system', new RunContext('run-2', attributes: ['conversation' => 'c-1'])),
        );
        $this->assertNull($selector->select('other', new RunContext('run-1')));
    }

    public function testListenerResolvesTheInstructionPerRun()
    {
        $registry = new YamlPromptRegistry([__DIR__.'/Fixtures']);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AgentInvocationStarted::class, new PromptInstructionListener(
            $registry,
            'support/system',
            version: '2026-08-v2',
            variables: ['shop_name' => 'ACME'],
            selector: new PercentageRolloutSelector('support/system', '2026-09-v3', '2026-08-v2', 100),
        ));

        $systemPrompt = null;
        $platform = new InMemoryPlatform(static function ($model, MessageBag $messages) use (&$systemPrompt): string {
            $systemPrompt = $messages->getSystemMessage()?->getContent();

            return 'Hello';
        });

        $agent = new Agent($platform, 'gpt-4o', instruction: 'Static instruction.', eventDispatcher: $dispatcher);
        $runContext = $agent->call('Hi', new Context(new RunContext('run-1')))->getMetadata()->get('run_context');

        $this->assertInstanceOf(RunContext::class, $runContext);
        $this->assertSame('support/system@2026-09-v3', (string) $runContext->getPrompt());
        $this->assertIsString($systemPrompt);
        $this->assertStringContainsString('Never promise a refund', $systemPrompt);
        $this->assertStringNotContainsString('Static instruction.', $systemPrompt);
    }
}
