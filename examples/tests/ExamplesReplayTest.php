<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Examples\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Runs every example that has a recorded cassette offline (the test itself sets CASSETTE=replay)
 * and asserts it still succeeds.
 *
 * Because each example drives the real Platform pipeline
 * (ModelClient -> RawHttpResult -> ResultConverter), a converter regression
 * makes the example throw or change its output, failing this test - without any
 * API credentials. Cassettes are recorded occasionally against the real APIs
 * (`./runner --record`) and committed under fixtures/.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ExamplesReplayTest extends TestCase
{
    #[DataProvider('provideRecordedExamples')]
    public function testExampleReplaysDeterministically(string $examplePath, ?string $goldenPath)
    {
        $process = new Process(['php', $examplePath], self::examplesDirectory(), ['CASSETTE' => 'replay']);
        $process->run();

        $this->assertSame(
            0,
            $process->getExitCode(),
            \sprintf("Example \"%s\" failed on replay:\n%s%s", $examplePath, $process->getOutput(), $process->getErrorOutput()),
        );

        if (null === $goldenPath) {
            return;
        }

        $this->assertSame(
            $this->normalize((string) file_get_contents($goldenPath)),
            $this->normalize($process->getOutput()),
            \sprintf('Example "%s" produced output differing from its recorded golden.', $examplePath),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string|null}>
     */
    public static function provideRecordedExamples(): iterable
    {
        $cassettes = self::examplesDirectory().'/fixtures';

        if (!is_dir($cassettes)) {
            return;
        }

        $finder = (new Finder())->files()->in($cassettes)->name('*.json')->sortByName();

        foreach ($finder as $cassette) {
            $relative = $cassette->getRelativePathname();
            $example = substr($relative, 0, -\strlen('.json')).'.php';
            $golden = $cassette->getPath().'/'.$cassette->getFilenameWithoutExtension().'.out';

            yield $example => [$example, is_file($golden) ? $golden : null];
        }
    }

    private static function examplesDirectory(): string
    {
        return \dirname(__DIR__);
    }

    private function normalize(string $output): string
    {
        return rtrim(str_replace("\r\n", "\n", $output))."\n";
    }
}
