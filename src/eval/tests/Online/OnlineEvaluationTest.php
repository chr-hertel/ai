<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Online;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Eval\Evaluator\Limits;
use Symfony\AI\Eval\Evaluator\TextAssertions;
use Symfony\AI\Eval\Evaluator\ToolsCalled;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\Online\EvaluateRun;
use Symfony\AI\Eval\Online\EvaluateRunHandler;
use Symfony\AI\Eval\Online\SamplingProcessor;
use Symfony\AI\Eval\Tests\Fixtures\SupportModel;
use Symfony\AI\Eval\Tests\Fixtures\SupportTools;
use Symfony\AI\Eval\Toolbox\FixtureToolbox;
use Symfony\AI\Feedback\InMemoryRecorder;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Feedback\Source;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class OnlineEvaluationTest extends TestCase
{
    public function testSampledRunsAreScoredAndRecordedAsFeedback()
    {
        $recorder = new InMemoryRecorder();
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            EvaluateRun::class => [new EvaluateRunHandler([new ToolsCalled(), new TextAssertions()], $recorder)],
        ]))]);

        $expectations = ['not_contains' => ['has been processed']];
        $sampleAll = new SamplingProcessor($bus, 1.0, $expectations, static fn (): float => 0.5);

        $toolbox = new FixtureToolbox(new Toolbox([new SupportTools()]), ['order_lookup' => ['SO-1' => 'delivered']]);
        $agent = new Agent(MockPlatformFactory::createPlatform(\Closure::fromCallable(new SupportModel())), 'support-model', inputProcessors: [new SystemPromptInputProcessor('You are the support assistant.')], outputProcessors: [$sampleAll], toolbox: $toolbox);

        $agent->call('I want my money back for SO-1.', ['run_context' => new RunContext('run-1')])->getContent();

        $feedback = $recorder->all('run-1', Signal::EvaluatorScore);
        $this->assertCount(1, $feedback);
        $this->assertSame('text', $feedback[0]->getName());
        $this->assertSame(Source::Evaluator, $feedback[0]->getSource());
        $this->assertSame(0.0, $feedback[0]->getValue());
    }

    public function testRunsOutsideTheSampleAreNotEvaluated()
    {
        $recorder = new InMemoryRecorder();
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            EvaluateRun::class => [new EvaluateRunHandler([new TextAssertions()], $recorder)],
        ]))]);

        $agent = new Agent(MockPlatformFactory::createPlatform('Hello'), 'gpt-4o', outputProcessors: [new SamplingProcessor($bus, 0.05, ['contains' => ['x']], static fn (): float => 0.5)]);
        $agent->call('Hi')->getContent();

        $this->assertSame([], $recorder->all());
    }

    public function testSampledRunsCarryTheirDuration()
    {
        $recorder = new InMemoryRecorder();
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            EvaluateRun::class => [new EvaluateRunHandler([new Limits()], $recorder)],
        ]))]);

        $platform = MockPlatformFactory::createPlatform(static function (): string {
            usleep(20_000);

            return 'Hello';
        });
        $agent = new Agent($platform, 'gpt-4o', outputProcessors: [new SamplingProcessor($bus, 1.0, ['max_latency' => 0.01], static fn (): float => 0.0)]);
        $agent->call('Hi', ['run_context' => new RunContext('run-1')])->getContent();

        $feedback = $recorder->all('run-1', Signal::EvaluatorScore);
        $this->assertCount(1, $feedback);
        $this->assertSame('limits', $feedback[0]->getName());
        $this->assertFalse($feedback[0]->getMetadata()['passed']);
    }

    public function testHandlerOfAnAgentOnlyScoresTheRunsOfThatAgent()
    {
        $recorder = new InMemoryRecorder();
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            EvaluateRun::class => [
                new EvaluateRunHandler([new TextAssertions()], $recorder, 'support'),
                new EvaluateRunHandler([new Limits()], $recorder, 'sales'),
            ],
        ]))]);

        $agent = new Agent(MockPlatformFactory::createPlatform('Hello'), 'gpt-4o', outputProcessors: [new SamplingProcessor($bus, 1.0, ['contains' => ['Hello']], static fn (): float => 0.0, 'support')]);
        $agent->call('Hi', ['run_context' => new RunContext('run-1')])->getContent();

        $feedback = $recorder->all('run-1', Signal::EvaluatorScore);
        $this->assertCount(1, $feedback);
        $this->assertSame('text', $feedback[0]->getName());
    }

    #[TestWith([-0.1])]
    #[TestWith([1.1])]
    public function testSampleRateMustBeAShare(float $sampleRate)
    {
        $this->expectException(InvalidArgumentException::class);

        new SamplingProcessor(new MessageBus(), $sampleRate);
    }

    public function testFullSampleRateEvaluatesEveryRun()
    {
        $recorder = new InMemoryRecorder();
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            EvaluateRun::class => [new EvaluateRunHandler([new TextAssertions()], $recorder)],
        ]))]);

        $agent = new Agent(MockPlatformFactory::createPlatform('Hello'), 'gpt-4o', outputProcessors: [new SamplingProcessor($bus, 1.0, ['contains' => ['Hello']])]);
        for ($i = 0; $i < 20; ++$i) {
            $agent->call('Hi', ['run_context' => new RunContext('run-'.$i)])->getContent();
        }

        $this->assertCount(20, $recorder->all());
    }
}
