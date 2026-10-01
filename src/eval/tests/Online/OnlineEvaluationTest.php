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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Eval\Evaluator\TextAssertions;
use Symfony\AI\Eval\Evaluator\ToolsCalled;
use Symfony\AI\Eval\Online\EvaluateRun;
use Symfony\AI\Eval\Online\EvaluateRunHandler;
use Symfony\AI\Eval\Online\SamplingProcessor;
use Symfony\AI\Eval\Tests\Fixtures\SupportModel;
use Symfony\AI\Eval\Tests\Fixtures\SupportTools;
use Symfony\AI\Eval\Toolbox\FixtureToolbox;
use Symfony\AI\Feedback\InMemoryRecorder;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Feedback\Source;
use Symfony\AI\Platform\Test\InMemoryPlatform;
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
        $agent = new Agent(new InMemoryPlatform(\Closure::fromCallable(new SupportModel())), 'support-model', instruction: 'You are the support assistant.', contextProcessors: [$sampleAll], toolbox: $toolbox);

        $agent->call('I want my money back for SO-1.', new Context(new RunContext('run-1')))->getContent();

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

        $agent = new Agent(new InMemoryPlatform('Hello'), 'gpt-4o', contextProcessors: [new SamplingProcessor($bus, 0.05, ['contains' => ['x']], static fn (): float => 0.5)]);
        $agent->call('Hi')->getContent();

        $this->assertSame([], $recorder->all());
    }
}
