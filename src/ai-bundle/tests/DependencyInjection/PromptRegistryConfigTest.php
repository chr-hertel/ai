<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Prompt\PromptInputProcessor;
use Symfony\AI\Agent\Prompt\PromptRegistryInterface;
use Symfony\AI\Agent\Prompt\YamlPromptRegistry;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class PromptRegistryConfigTest extends TestCase
{
    public function testPromptRegistryLoadsThePaths()
    {
        $container = $this->buildContainer(['prompt_registry' => ['paths' => ['/app/prompts']]]);

        $registry = $container->getDefinition('ai.prompt_registry');
        $this->assertSame(YamlPromptRegistry::class, $registry->getClass());
        $this->assertSame([['/app/prompts']], $registry->getArguments());
        $this->assertSame('ai.prompt_registry', (string) $container->getAlias(PromptRegistryInterface::class));
    }

    public function testAgentUsesAPromptOfTheRegistry()
    {
        $container = $this->buildContainer([
            'prompt_registry' => ['paths' => ['/app/prompts']],
            'agent' => ['support' => [
                'model' => 'gpt-4o-mini',
                'prompt' => ['registry' => 'support/system', 'version' => 'v2', 'variables' => ['shop_name' => 'Acme']],
            ]],
        ]);

        $processor = $container->getDefinition('ai.agent.support.prompt_processor');
        $this->assertSame(PromptInputProcessor::class, $processor->getClass());
        $this->assertEquals([new Reference('ai.prompt_registry'), 'support/system', 'v2', ['shop_name' => 'Acme']], $processor->getArguments());
        $this->assertSame([['agent' => 'ai.agent.support', 'priority' => -30]], $processor->getTag('ai.agent.input_processor'));
        $this->assertFalse($container->hasDefinition('ai.agent.support.system_prompt_processor'));
    }

    public function testRegistryPromptRequiresARegistry()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Agent "support" uses the prompt "support/system" of the registry, but no "prompt_registry" is configured.');

        $this->buildContainer(['agent' => ['support' => ['model' => 'gpt-4o-mini', 'prompt' => ['registry' => 'support/system']]]]);
    }

    public function testRegistryPromptCannotBeCombinedWithText()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot combine "text", "file" and "registry" for prompt.');

        $this->buildContainer(['agent' => ['support' => ['model' => 'gpt-4o-mini', 'prompt' => ['registry' => 'support/system', 'text' => 'Be nice.']]]]);
    }

    public function testVersionRequiresARegistryPrompt()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The prompt "version" and "variables" are only used with "registry".');

        $this->buildContainer(['agent' => ['support' => ['model' => 'gpt-4o-mini', 'prompt' => ['text' => 'Be nice.', 'version' => 'v2']]]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildContainer(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.build_dir', 'public');

        (new AiBundle())->getContainerExtension()->load(['ai' => $config], $container);

        return $container;
    }
}
