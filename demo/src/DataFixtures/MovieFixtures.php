<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\DataFixtures;

use App\Movies\Movie;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * Loads the movies from the Markdown files, the embedding listener embeds them on flush.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MovieFixtures extends Fixture
{
    public function __construct(
        #[Autowire('%movies_dir%')]
        private readonly string $directory,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $files = (new Finder())->files()->in($this->directory)->name('*.md')->sortByName();

        foreach ($files as $file) {
            $manager->persist(Movie::fromFile($file->getRealPath()));
        }

        $manager->flush();
    }
}
