<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Movie;

use App\Movies\MarkdownDirectoryLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(MarkdownDirectoryLoader::class)]
final class MarkdownDirectoryLoaderTest extends TestCase
{
    private string $directory;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/movie-loader-'.uniqid();
        $this->fs = new Filesystem();
        $this->fs->mkdir($this->directory);

        $this->fs->dumpFile($this->directory.'/one.md', "# One\n\nContent one.\n");
        $this->fs->dumpFile($this->directory.'/two.md', "# Two\n\nContent two.\n");
        $this->fs->dumpFile($this->directory.'/skipme.txt', 'ignored');
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->directory);
    }

    public function testLoadYieldsOneDocumentPerMarkdownFile(): void
    {
        $loader = new MarkdownDirectoryLoader();

        $documents = iterator_to_array($loader->load($this->directory), false);

        $this->assertCount(2, $documents);
        $this->assertContainsOnlyInstancesOf(TextDocument::class, $documents);

        $titles = array_map(static fn (TextDocument $d) => $d->getMetadata()['title'] ?? null, $documents);
        sort($titles);
        $this->assertSame(['One', 'Two'], $titles);
    }

    public function testLoadPassesOptionsToInnerMarkdownLoader(): void
    {
        $this->fs->dumpFile($this->directory.'/formatted.md', "# Bold\n\n**strong** text with `code`.\n");

        $loader = new MarkdownDirectoryLoader();

        $documents = iterator_to_array($loader->load($this->directory, ['strip_formatting' => true]), false);
        $formatted = null;
        foreach ($documents as $document) {
            if (($document->getMetadata()['title'] ?? null) === 'Bold') {
                $formatted = $document;
                break;
            }
        }

        $this->assertNotNull($formatted);
        $this->assertStringNotContainsString('**', $formatted->getContent());
        $this->assertStringNotContainsString('`', $formatted->getContent());
    }

    public function testLoadThrowsWhenSourceIsNotADirectory(): void
    {
        $loader = new MarkdownDirectoryLoader();

        $this->expectException(InvalidArgumentException::class);

        iterator_to_array($loader->load(__FILE__), false);
    }

    public function testLoadThrowsWhenSourceIsNull(): void
    {
        $loader = new MarkdownDirectoryLoader();

        $this->expectException(InvalidArgumentException::class);

        iterator_to_array($loader->load(null), false);
    }
}
