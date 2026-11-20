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
use Symfony\AI\Agent\Budget\RunBudget;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\AI\Platform\Cost\CostCalculator;
use Symfony\AI\Platform\Cost\PriceTable;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class BudgetConfigTest extends TestCase
{
    public function testTokenBudgetLimitsTheRunsOfItsAgent()
    {
        $container = $this->buildContainer(['support' => ['model' => 'gpt-4o-mini', 'budget' => ['max_tokens_per_run' => 20000]]]);

        $budget = $container->getDefinition('ai.agent.support.budget');
        $this->assertSame(RunBudget::class, $budget->getClass());
        $this->assertSame(20000, $budget->getArgument('$maxTokens'));
        $this->assertNull($budget->getArgument('$maxCost'));
        $this->assertTrue($budget->getArgument('$onlyProcessedRuns'));
        $this->assertFalse($budget->getArguments()['$costCalculator'] ?? false);
        $this->assertSame([['agent' => 'ai.agent.support', 'priority' => -1024]], $budget->getTag('ai.agent.input_processor'));
        $this->assertSame([['event' => ToolCallsExecuted::class]], $budget->getTag('kernel.event_listener'));
    }

    public function testCostBudgetReadsThePricesOfThePlatformCatalog()
    {
        $container = $this->buildContainer(['support' => [
            'platform' => 'ai.platform.openai',
            'model' => 'gpt-4o-mini',
            'budget' => ['max_cost_per_run' => 0.25, 'message' => 'Too expensive.'],
        ]]);

        $budget = $container->getDefinition('ai.agent.support.budget');
        $this->assertSame(0.25, $budget->getArgument('$maxCost'));
        $this->assertSame('Too expensive.', $budget->getArgument('$message'));

        $calculator = $budget->getArgument('$costCalculator');
        $this->assertInstanceOf(Definition::class, $calculator);
        $this->assertSame(CostCalculator::class, $calculator->getClass());
        $prices = $calculator->getArgument(0);
        $this->assertInstanceOf(Definition::class, $prices);
        $this->assertSame([PriceTable::class, 'fromModelCatalog'], $prices->getFactory());
        $this->assertEquals([new Reference('ai.platform.openai'), 'getModelCatalog'], $prices->getArgument(0)->getFactory());
    }

    public function testBudgetNeedsALimit()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('A budget needs "max_tokens_per_run" or "max_cost_per_run".');

        $this->buildContainer(['support' => ['model' => 'gpt-4o-mini', 'budget' => []]]);
    }

    public function testCatalogModelsCanHaveAPrice()
    {
        $container = $this->buildContainer([], ['openai' => ['api_key' => 'sk-test', 'model_catalog' => ['models' => [
            'my-fine-tune' => ['capabilities' => ['input-text', 'output-text'], 'price' => ['input' => 3.0, 'output' => 12.0]],
        ]]]]);

        $catalog = $container->getDefinition('ai.platform.openai')->getArgument(2);
        $this->assertInstanceOf(Definition::class, $catalog);
        $models = $catalog->getArgument(0)[0]->getArgument(0);
        $this->assertSame(['input' => 3.0, 'output' => 12.0], $models['my-fine-tune']['price']);
    }

    /**
     * @param array<string, mixed> $agents
     * @param array<string, mixed> $platforms
     */
    private function buildContainer(array $agents, array $platforms = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.build_dir', 'public');

        (new AiBundle())->getContainerExtension()->load(['ai' => array_filter(['agent' => $agents, 'platform' => $platforms])], $container);

        return $container;
    }
}
