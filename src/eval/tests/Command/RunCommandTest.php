<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Eval\Command\RunCommand;
use Symfony\AI\Eval\Dataset;
use Symfony\AI\Eval\Evaluator\TextAssertions;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\Gate\PassRateGate;
use Symfony\AI\Eval\Report\Report;
use Symfony\AI\Eval\Report\ReportPublisherInterface;
use Symfony\AI\Eval\Suite;
use Symfony\AI\Eval\Variant;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RunCommandTest extends TestCase
{
    public function testRunsTheSuiteAndWritesReports()
    {
        $dir = sys_get_temp_dir().'/ai-eval-'.uniqid();
        $suite = new Suite(
            'greetings',
            [Dataset::fromArray(['name' => 'greetings', 'cases' => [['id' => 'hello', 'input' => 'Hi', 'expect' => ['contains' => ['hello']]]]])],
            [new Variant('polite', ['answer' => 'Hello!']), new Variant('rude', ['answer' => 'What?'])],
            static fn (Variant $variant): Agent => new Agent(MockPlatformFactory::createPlatform($variant->get('answer')), 'model'),
            [new TextAssertions()],
            [new PassRateGate(1.0)],
        );

        $tester = new CommandTester(new RunCommand([$suite]));

        $this->assertSame(Command::SUCCESS, $tester->execute(['suite' => 'greetings', '--variant' => ['polite'], '--report' => ['json:'.$dir.'/report.json']]));
        $this->assertStringContainsString('polite', $tester->getDisplay());
        $this->assertFileExists($dir.'/report.json');

        $this->assertSame(Command::FAILURE, $tester->execute(['suite' => 'greetings']));
        $this->assertStringContainsString('FAIL rude', $tester->getDisplay());

        unlink($dir.'/report.json');
        rmdir($dir);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidReports(): iterable
    {
        yield 'unknown format' => ['yaml:'.sys_get_temp_dir().'/report.yaml', 'The report format "yaml" is not supported.'];
        yield 'missing path' => ['json', 'The report option "json" must be given as format:path.'];
        yield 'directory is a file' => ['json:'.__FILE__.'/report.json', 'cannot be created'];
    }

    #[DataProvider('invalidReports')]
    public function testInvalidReportsFailBeforeTheRun(string $report, string $message)
    {
        $called = false;
        $suite = new Suite(
            'greetings',
            [Dataset::fromArray(['name' => 'greetings', 'cases' => [['id' => 'hello', 'input' => 'Hi']]])],
            [new Variant('polite')],
            static function () use (&$called): Agent {
                $called = true;

                return new Agent(MockPlatformFactory::createPlatform('Hello!'), 'model');
            },
            [new TextAssertions()],
        );

        try {
            (new CommandTester(new RunCommand([$suite])))->execute(['suite' => 'greetings', '--report' => [$report]]);
            $this->fail('The report option should have been rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }

        $this->assertFalse($called);
    }

    public function testPushesTheResults()
    {
        $publisher = new class implements ReportPublisherInterface {
            public ?Report $report = null;

            public function publish(Report $report): void
            {
                $this->report = $report;
            }
        };

        $tester = new CommandTester(new RunCommand([$this->greetingsSuite()], publishers: ['langfuse' => $publisher]));

        $this->assertSame(Command::SUCCESS, $tester->execute(['suite' => 'greetings', '--push' => ['langfuse']]));
        $this->assertStringContainsString('Pushed the results to langfuse', $tester->getDisplay());
        $this->assertSame('greetings', $publisher->report?->getSuite());
    }

    public function testUnknownBackendFailsBeforeTheRun()
    {
        $called = false;
        $suite = $this->greetingsSuite(static function () use (&$called): void {
            $called = true;
        });

        try {
            (new CommandTester(new RunCommand([$suite], publishers: ['langfuse' => $this->createStub(ReportPublisherInterface::class)])))->execute(['suite' => 'greetings', '--push' => ['phoenix']]);
            $this->fail('The push option should have been rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The results cannot be pushed to "phoenix", available backends: "langfuse".', $e->getMessage());
        }

        $this->assertFalse($called);
    }

    /**
     * @param (\Closure(): void)|null $onAgent
     */
    private function greetingsSuite(?\Closure $onAgent = null): Suite
    {
        return new Suite(
            'greetings',
            [Dataset::fromArray(['name' => 'greetings', 'cases' => [['id' => 'hello', 'input' => 'Hi']]])],
            [new Variant('polite')],
            static function () use ($onAgent): Agent {
                if (null !== $onAgent) {
                    $onAgent();
                }

                return new Agent(MockPlatformFactory::createPlatform('Hello!'), 'model');
            },
            [new TextAssertions()],
        );
    }
}
