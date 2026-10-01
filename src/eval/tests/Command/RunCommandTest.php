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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Eval\Command\RunCommand;
use Symfony\AI\Eval\Dataset;
use Symfony\AI\Eval\Evaluator\TextAssertions;
use Symfony\AI\Eval\Gate\PassRateGate;
use Symfony\AI\Eval\Suite;
use Symfony\AI\Eval\Variant;
use Symfony\AI\Platform\Test\InMemoryPlatform;
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
            static fn (Variant $variant): Agent => new Agent(new InMemoryPlatform($variant->get('answer')), 'model'),
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
}
