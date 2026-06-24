<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Movies;

use Symfony\AI\Store\Document\Loader\MarkdownLoader;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Finder\Finder;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MarkdownDirectoryLoader implements LoaderInterface
{
    public function __construct(
        private readonly MarkdownLoader $markdownLoader = new MarkdownLoader(),
    ) {
    }

    public function load(?string $source = null, array $options = []): iterable
    {
        if (null === $source || !is_dir($source)) {
            throw new InvalidArgumentException(\sprintf('MarkdownDirectoryLoader requires an existing directory as source, "%s" given.', $source ?? 'null'));
        }

        $finder = (new Finder())
            ->files()
            ->in($source)
            ->name('*.md')
            ->sortByName();

        foreach ($finder as $file) {
            yield from $this->markdownLoader->load($file->getRealPath(), $options);
        }
    }
}
