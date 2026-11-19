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
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Prompt\InMemoryPromptRegistry;
use Symfony\AI\Agent\Prompt\PercentageRolloutSelector;
use Symfony\AI\Agent\Prompt\YamlPromptRegistry;
use Symfony\AI\Agent\Run\RunContext;

final class PromptRegistryTest extends TestCase
{
    public function testYamlRegistryLoadsVersionsInOrder()
    {
        $registry = new YamlPromptRegistry([__DIR__.'/Fixtures']);

        $this->assertSame(['2026-08-v2', '2026-09-v3'], $registry->versions('support/system'));
        $this->assertSame('2026-09-v3', $registry->get('support/system')->getReference()->getVersion());
        $this->assertSame("You are the support assistant of ACME.\n", $registry->get('support/system', '2026-08-v2')->render(['shop_name' => 'ACME']));
    }

    public function testYamlRegistryLoadsYmlFiles()
    {
        $registry = new YamlPromptRegistry([__DIR__.'/Fixtures/yml']);

        $this->assertSame('Hello Alice.', $registry->get('greeting')->render(['name' => 'Alice']));
    }

    public function testYamlRegistryRejectsAVersionWithoutTemplate()
    {
        $registry = new YamlPromptRegistry([__DIR__.'/Fixtures/missing-template']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The version "v1" of the prompt "greeting"');

        $registry->get('greeting');
    }

    public function testYamlRegistryRejectsAVersionDefinedTwice()
    {
        $registry = new YamlPromptRegistry([__DIR__.'/Fixtures/duplicate']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is defined more than once');

        $registry->get('greeting');
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

    public function testRolloutSelectorKeepsAUserOnOneVersion()
    {
        $selector = new PercentageRolloutSelector('support/system', 'v3', 'v2', 50);

        $versions = array_map(static fn (int $i): ?string => $selector->select('support/system', new RunContext('run-'.$i, 'customer-1')), range(1, 20));

        $this->assertCount(1, array_unique($versions));
    }

    public function testRolloutsOfDifferentCandidatesPickDifferentConversations()
    {
        $first = new PercentageRolloutSelector('support/system', 'v3', 'v2', 10);
        $second = new PercentageRolloutSelector('support/system', 'v4', 'v2', 10);

        $firstPicks = $secondPicks = [];
        foreach (range(1, 500) as $i) {
            $runContext = new RunContext('run-'.$i, attributes: ['conversation' => 'c-'.$i]);
            if ('v3' === $first->select('support/system', $runContext)) {
                $firstPicks[] = $i;
            }
            if ('v4' === $second->select('support/system', $runContext)) {
                $secondPicks[] = $i;
            }
        }

        $this->assertNotSame($firstPicks, $secondPicks);
        $this->assertLessThan(\count($firstPicks), \count(array_intersect($firstPicks, $secondPicks)));
    }
}
