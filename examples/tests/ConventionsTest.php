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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Guards the invariants of the example corpus that reviewers cannot hold in their head.
 *
 * Every one of these encodes drift that actually happened: bridges used without their package
 * being required (which "link" cannot repair, so the examples died on a class-not-found), .env
 * growing to twice its useful size with values the Docker setup already pins, and bridges landing
 * without a cassette so that nothing ever replayed them.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConventionsTest extends TestCase
{
    public function testEveryUsedBridgeHasItsPackageRequired()
    {
        $required = self::requiredPackages();
        $missing = [];

        foreach (self::usedBridges() as $namespace => $package) {
            if (!isset($required[$package])) {
                $missing[$package] = $namespace;
            }
        }

        $this->assertSame([], $missing, \sprintf(
            "Examples use these bridges without examples/composer.json requiring their package:\n%s\n".
            'Note that "link" only replaces vendor directories that already exist, so it cannot compensate.',
            implode("\n", array_map(
                static fn (string $namespace, string $package): string => \sprintf('  %s => %s', $namespace, $package),
                $missing,
                array_keys($missing),
            )),
        ));
    }

    public function testNoBridgePackageIsRequiredWithoutAnExample()
    {
        $used = array_flip(self::usedBridges());
        $unused = [];

        foreach (self::bridgePackages() as $package) {
            if (isset(self::requiredPackages()[$package]) && !isset($used[$package])) {
                $unused[] = $package;
            }
        }

        sort($unused);

        $this->assertSame([], $unused, \sprintf(
            "examples/composer.json requires these bridge packages, but no example uses them:\n  %s\n".
            'Either add an example for the bridge or drop the requirement.',
            implode("\n  ", $unused),
        ));
    }

    /**
     * .env is for secrets, so every entry in it is something the reader has to supply.
     *
     * A value that already has a default is by definition not a secret: it is a host the Docker
     * setup pins, a local daemon's default or a parameter the example chose, and it belongs in the
     * example itself, where the reader can see it.
     */
    public function testEnvOnlyDeclaresSecrets()
    {
        $withDefaults = [];

        foreach (file(self::examplesDirectory().'/.env', \FILE_IGNORE_NEW_LINES) as $line) {
            if (1 === preg_match('/^([A-Z][A-Z0-9_]*)=(.+)$/', $line, $matches)) {
                $withDefaults[$matches[1]] = $matches[2];
            }
        }

        $this->assertSame([], $withDefaults, \sprintf(
            "These .env entries carry a default value, so they are not secrets:\n  %s\n".
            'Inline the value in the examples that read it and drop the variable.',
            implode("\n  ", array_keys($withDefaults)),
        ));
    }

    public function testEnvDeclaresExactlyWhatTheExamplesRead()
    {
        $declared = [];
        foreach (file(self::examplesDirectory().'/.env', \FILE_IGNORE_NEW_LINES) as $line) {
            if (1 === preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $matches)) {
                $declared[] = $matches[1];
            }
        }
        sort($declared);

        $read = [];
        foreach (self::exampleSources() as $source) {
            preg_match_all("/(?:env|require_env)\(([^)]*)\)/", $source, $calls);
            foreach ($calls[1] as $arguments) {
                preg_match_all("/'([A-Z][A-Z0-9_]+)'/", $arguments, $names);
                $read = array_merge($read, $names[1]);
            }
        }
        $read = array_values(array_unique($read));
        sort($read);

        $this->assertSame($read, $declared, 'Every secret an example reads belongs in .env, and nothing else does.');
    }

    /**
     * Every platform bridge an example exercises should have at least one recorded cassette, so a
     * result-converter regression in it fails the build without any credentials.
     *
     * Bridges that predate this rule are listed in tests/cassette-debt.txt. The list may only
     * shrink: a bridge that is not on it must come with a cassette, and one that is on it must be
     * taken off as soon as it has one.
     */
    public function testEveryPlatformBridgeIsReplayed()
    {
        $recorded = [];
        foreach (self::exampleSources(withCassetteOnly: true) as $source) {
            preg_match_all('/Symfony\\\\AI\\\\Platform\\\\Bridge\\\\(\w+)/', $source, $matches);
            $recorded = array_merge($recorded, $matches[1]);
        }
        $recorded = array_unique($recorded);

        $used = [];
        foreach (array_keys(self::usedBridges()) as $namespace) {
            if (1 === preg_match('/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\(\w+)$/', $namespace, $matches)) {
                $used[] = $matches[1];
            }
        }

        $debt = array_values(array_filter(array_map(
            'trim',
            file(__DIR__.'/cassette-debt.txt', \FILE_IGNORE_NEW_LINES),
        ), static fn (string $line): bool => '' !== $line && !str_starts_with($line, '#')));

        $uncovered = array_values(array_diff($used, $recorded, $debt));
        sort($uncovered);

        $this->assertSame([], $uncovered, \sprintf(
            "These platform bridges have no recorded example, so nothing replays them:\n  %s\n".
            'Record one with "./runner --record <directory>" and commit the cassette and golden.',
            implode("\n  ", $uncovered),
        ));

        $settled = array_values(array_intersect($debt, $recorded));
        sort($settled);

        $this->assertSame([], $settled, \sprintf(
            "These bridges are recorded now and must be removed from tests/cassette-debt.txt:\n  %s",
            implode("\n  ", $settled),
        ));
    }

    /**
     * INDEX.md is the one view the tree cannot give: the capability matrix across platform bridges.
     *
     * It is generated, so it is only useful while it is current - regenerate with "./build-index".
     */
    public function testIndexIsUpToDate()
    {
        $committed = (string) file_get_contents(self::examplesDirectory().'/INDEX.md');

        $process = new Process(['php', self::examplesDirectory().'/build-index'], self::examplesDirectory());
        $process->run();

        $this->assertSame(0, $process->getExitCode(), 'build-index failed: '.$process->getErrorOutput());

        $regenerated = (string) file_get_contents(self::examplesDirectory().'/INDEX.md');

        if ($committed !== $regenerated) {
            file_put_contents(self::examplesDirectory().'/INDEX.md', $committed);
        }

        $this->assertSame($committed, $regenerated, 'INDEX.md is stale, run "./build-index" and commit the result.');
    }

    /**
     * Bridge namespace => Composer package, for every bridge an example imports.
     *
     * @return array<string, string>
     */
    private static function usedBridges(): array
    {
        $packages = self::bridgePackages();
        $used = [];

        foreach (self::exampleSources() as $source) {
            preg_match_all('/Symfony\\\\AI\\\\(?:Platform|Agent|Store|Chat)\\\\Bridge\\\\\w+/', $source, $matches);
            foreach ($matches[0] as $namespace) {
                if (isset($packages[$namespace])) {
                    $used[$namespace] = $packages[$namespace];
                }
            }
        }

        ksort($used);

        return $used;
    }

    /**
     * Bridge namespace => Composer package, for every bridge in the monorepo.
     *
     * @return array<string, string>
     */
    private static function bridgePackages(): array
    {
        static $packages = null;

        if (null !== $packages) {
            return $packages;
        }

        $packages = [];
        $manifests = (new Finder())
            ->files()
            ->in(\dirname(self::examplesDirectory()).'/src/*/src/Bridge/*')
            ->depth(0)
            ->name('composer.json');

        foreach ($manifests as $manifest) {
            $definition = json_decode((string) file_get_contents($manifest->getRealPath()), true, flags: \JSON_THROW_ON_ERROR);

            foreach (array_keys($definition['autoload']['psr-4'] ?? []) as $namespace) {
                $packages[rtrim((string) $namespace, '\\')] = $definition['name'];
            }
        }

        return $packages;
    }

    /**
     * @return array<string, true>
     */
    private static function requiredPackages(): array
    {
        $definition = json_decode(
            (string) file_get_contents(self::examplesDirectory().'/composer.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        return array_fill_keys(array_keys($definition['require']), true);
    }

    /**
     * @return iterable<string>
     */
    private static function exampleSources(bool $withCassetteOnly = false): iterable
    {
        $examples = (new Finder())
            ->files()
            ->in(self::examplesDirectory())
            ->name('*.php')
            ->exclude(['vendor', 'tests', 'var']);

        foreach ($examples as $example) {
            if ($withCassetteOnly) {
                $relative = substr($example->getRelativePathname(), 0, -\strlen('.php'));

                if (!is_file(__DIR__.'/fixtures/'.$relative.'.json')) {
                    continue;
                }
            }

            yield (string) file_get_contents($example->getRealPath());
        }
    }

    private static function examplesDirectory(): string
    {
        return \dirname(__DIR__);
    }
}
