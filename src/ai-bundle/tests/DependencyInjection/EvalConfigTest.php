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
use Symfony\AI\AiBundle\AiBundle;
use Symfony\AI\AiBundle\DependencyInjection\ProcessorCompilerPass;
use Symfony\AI\AiBundle\Eval\SuiteAgentFactory;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\Eval\Bridge\Langfuse\Command\DatasetAddCommand;
use Symfony\AI\Eval\Bridge\Langfuse\Command\DatasetPullCommand;
use Symfony\AI\Eval\Bridge\Langfuse\LangfuseDatasetSource;
use Symfony\AI\Eval\Bridge\Langfuse\LangfuseReportPublisher;
use Symfony\AI\Eval\Command\CalibrateCommand;
use Symfony\AI\Eval\Command\RunCommand;
use Symfony\AI\Eval\Evaluator\LlmJudge;
use Symfony\AI\Eval\Evaluator\Similarity;
use Symfony\AI\Eval\Evaluator\ToolsCalled;
use Symfony\AI\Eval\Gate\CostPerCaseGate;
use Symfony\AI\Eval\Gate\MinKappaGate;
use Symfony\AI\Eval\Gate\PassRateGate;
use Symfony\AI\Eval\Gate\RegressionGate;
use Symfony\AI\Eval\Online\EvaluateRunHandler;
use Symfony\AI\Eval\Online\SamplingProcessor;
use Symfony\AI\Eval\Suite;
use Symfony\AI\Eval\Variant;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class EvalConfigTest extends TestCase
{
    public function testEvaluatorsCallingAModelAreConfiguredByName()
    {
        $container = $this->buildContainer(['eval' => ['evaluators' => [
            'support_judge' => ['type' => 'llm_judge', 'platform' => 'ai.platform.openai', 'model' => 'gpt-4o', 'threshold' => 0.8],
            'similar' => ['type' => 'similarity', 'model' => 'text-embedding-3-small'],
        ]]]);

        $judge = $container->getDefinition('ai.eval.evaluator.support_judge');
        $this->assertSame(LlmJudge::class, $judge->getClass());
        $this->assertEquals(new Reference('ai.platform.openai'), $judge->getArgument('$platform'));
        $this->assertSame('gpt-4o', $judge->getArgument('$model'));
        $this->assertSame(0.8, $judge->getArgument('$threshold'));
        $this->assertSame('support_judge', $judge->getArgument('$name'));
        $this->assertTrue($judge->hasTag('ai.eval.judge'));

        $similarity = $container->getDefinition('ai.eval.evaluator.similar');
        $this->assertSame(Similarity::class, $similarity->getClass());
        $this->assertFalse($similarity->hasTag('ai.eval.judge'));

        $this->assertSame(ToolsCalled::class, $container->getDefinition('ai.eval.evaluator.tools_called')->getClass());

        $calibrate = $container->getDefinition('ai.eval.command.calibrate');
        $this->assertSame(CalibrateCommand::class, $calibrate->getClass());
        $this->assertEquals([new TaggedIteratorArgument('ai.eval.judge'), new TaggedIteratorArgument('ai.eval.labeled_runs', 'name')], $calibrate->getArguments());
    }

    public function testSuiteTestsAConfiguredAgent()
    {
        $container = $this->buildContainer([
            'prompt_registry' => ['paths' => ['/app/prompts']],
            'agent' => ['support' => [
                'platform' => 'ai.platform.anthropic',
                'model' => 'claude-sonnet-4-5',
                'prompt' => ['registry' => 'support/system', 'variables' => ['shop_name' => 'Acme']],
            ]],
            'eval' => ['suites' => ['support' => [
                'agent' => 'support',
                'datasets' => ['/app/evals/support.yaml'],
                'repetitions' => 2,
                'variants' => [
                    'baseline' => ['prompt_version' => 'v2'],
                    'cheap' => ['platform' => 'ai.platform.openai', 'model' => 'gpt-4o-mini'],
                ],
                'evaluators' => ['tools_called', 'app.evaluator'],
                'gates' => [
                    'pass_rate' => 0.9,
                    'label_pass_rate' => ['refund' => 0.95],
                    'regression' => ['baseline' => 'baseline'],
                    'max_cost_per_case' => 0.05,
                    'min_kappa' => ['judge' => 'tools_called', 'runs' => 'app.labeled_runs', 'min' => 0.6],
                ],
            ]]],
        ]);
        (new ProcessorCompilerPass())->process($container);

        $suite = $container->getDefinition('ai.eval.suite.support');
        $this->assertSame(Suite::class, $suite->getClass());
        $this->assertTrue($suite->hasTag('ai.eval.suite'));
        $this->assertSame('support', $suite->getArgument(0));

        $variants = $suite->getArgument(2);
        $this->assertSame(Variant::class, $variants[0]->getClass());
        $this->assertSame(['baseline', ['prompt_version' => 'v2', 'model' => 'claude-sonnet-4-5']], $variants[0]->getArguments());
        $this->assertSame(['cheap', ['platform' => 'ai.platform.openai', 'model' => 'gpt-4o-mini']], $variants[1]->getArguments());

        $this->assertEquals([new Reference('ai.eval.evaluator.tools_called'), new Reference('app.evaluator')], $suite->getArgument(4));
        $this->assertEquals(
            [[PassRateGate::class, [0.9]], [PassRateGate::class, [0.95, 'refund']], [RegressionGate::class, ['baseline', 0.02]], [CostPerCaseGate::class, [0.05]], [MinKappaGate::class, [new Reference('ai.eval.evaluator.tools_called'), new Reference('app.labeled_runs'), 0.6]]],
            array_map(static fn (Definition $gate): array => [$gate->getClass(), $gate->getArguments()], $suite->getArgument(5)),
        );
        $this->assertSame(2, $suite->getArgument(6));
        $this->assertNull($suite->getArgument(7), 'The agent has no tools');

        $factory = $container->getDefinition('ai.eval.suite.support.agent_factory');
        $this->assertSame(SuiteAgentFactory::class, $factory->getClass());
        $this->assertSame('ai.platform.anthropic', $factory->getArgument('$platform'));
        $this->assertSame('support/system', $factory->getArgument('$promptName'));
        $this->assertSame(['shop_name' => 'Acme'], $factory->getArgument('$promptVariables'));
        $this->assertContainsEquals(new Reference('ai.agent.support.prompt_processor'), $factory->getArgument('$inputProcessors'));

        $command = $container->getDefinition('ai.eval.command.run');
        $this->assertSame(RunCommand::class, $command->getClass());
        $this->assertEquals(new TaggedIteratorArgument('ai.eval.suite'), $command->getArgument('$suites'));
    }

    public function testLangfuseDatasetsAndPushing()
    {
        $container = $this->buildContainer([
            'agent' => ['support' => ['model' => 'gpt-4o-mini']],
            'eval' => [
                'langfuse' => ['public_key' => 'pk', 'secret_key' => 'sk', 'host' => 'http://langfuse'],
                'suites' => ['support' => ['agent' => 'support', 'datasets' => ['/app/evals/support.yaml']]],
            ],
        ]);

        $source = $container->getDefinition('ai.eval.langfuse.dataset_source');
        $this->assertSame(LangfuseDatasetSource::class, $source->getClass());
        $this->assertEquals([new Reference('http_client'), 'http://langfuse', 'pk', 'sk'], $source->getArguments());
        $this->assertSame(DatasetPullCommand::class, $container->getDefinition('ai.eval.command.dataset_pull')->getClass());
        $this->assertSame(DatasetAddCommand::class, $container->getDefinition('ai.eval.command.dataset_add')->getClass());

        $publisher = $container->getDefinition('ai.eval.langfuse.report_publisher');
        $this->assertSame(LangfuseReportPublisher::class, $publisher->getClass());
        $this->assertSame([['name' => 'langfuse']], $publisher->getTag('ai.eval.report_publisher'));
        $this->assertEquals(new TaggedIteratorArgument('ai.eval.report_publisher', 'name'), $container->getDefinition('ai.eval.command.run')->getArgument('$publishers'));
    }

    public function testDefaultVariantUsesTheAgent()
    {
        $container = $this->buildContainer([
            'agent' => ['support' => ['model' => 'gpt-4o-mini']],
            'eval' => ['suites' => ['support' => ['agent' => 'support', 'datasets' => ['/app/evals/support.yaml']]]],
        ]);

        $variants = $container->getDefinition('ai.eval.suite.support')->getArgument(2);
        $this->assertCount(1, $variants);
        $this->assertSame(['default', ['model' => 'gpt-4o-mini']], $variants[0]->getArguments());
    }

    public function testSuiteRequiresAConfiguredAgent()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The eval suite "support" tests the agent "support", which is not configured.');

        $this->buildContainer(['eval' => ['suites' => ['support' => ['agent' => 'support', 'datasets' => ['/app/evals/support.yaml']]]]]);
    }

    public function testOnlineEvaluationScoresASampleOfTheRuns()
    {
        $container = $this->buildContainer([
            'agent' => ['support' => [
                'model' => 'gpt-4o-mini',
                'online_evaluation' => ['sample_rate' => 0.1, 'evaluators' => ['limits'], 'expectations' => ['max_tool_calls' => 5]],
            ]],
            'feedback' => ['recorders' => ['services' => ['app.recorder']]],
        ]);

        $sampling = $container->getDefinition('ai.agent.support.online_evaluation');
        $this->assertSame(SamplingProcessor::class, $sampling->getClass());
        $this->assertEquals([new Reference('messenger.default_bus'), 0.1, ['max_tool_calls' => 5], null, 'support'], $sampling->getArguments());
        $this->assertSame([['agent' => 'ai.agent.support']], $sampling->getTag('ai.agent.output_processor'));

        $handler = $container->getDefinition('ai.agent.support.online_evaluation_handler');
        $this->assertSame(EvaluateRunHandler::class, $handler->getClass());
        $this->assertEquals([[new Reference('ai.eval.evaluator.limits')], new Reference('ai.feedback.recorder'), 'support'], $handler->getArguments());
        $this->assertTrue($handler->hasTag('messenger.message_handler'));
    }

    public function testOnlineEvaluationRequiresFeedback()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Online evaluation records its scores as feedback, configure "feedback" too.');

        $this->buildContainer(['agent' => ['support' => [
            'model' => 'gpt-4o-mini',
            'online_evaluation' => ['sample_rate' => 0.1, 'evaluators' => ['limits']],
        ]]]);
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
