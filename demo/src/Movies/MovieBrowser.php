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

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Interactive movie browser, rendered via Stimulus with HTTP-based live actions.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsLiveComponent('movie_browser')]
final class MovieBrowser
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp]
    public ?string $selectedSlug = null;

    /** @var list<Movie> */
    private ?array $results = null;

    public function __construct(
        private readonly MovieRepository $movies,
    ) {
    }

    /**
     * Search movies by title or content.
     *
     * @param string $query Search term to filter movies by title or director
     */
    #[LiveAction]
    public function search(#[LiveArg] string $query = ''): string
    {
        $this->query = $query;
        $this->selectedSlug = null;

        $movies = $this->getMovies();

        return \sprintf('Found %d movie(s) matching "%s".', \count($movies), $this->query);
    }

    /**
     * Select a movie to view its full details.
     *
     * @param string $slug The movie slug identifier
     */
    #[LiveAction]
    public function select(#[LiveArg] string $slug): string
    {
        $movie = $this->movies->find($slug);
        if (null === $movie) {
            return \sprintf('Movie "%s" not found.', $slug);
        }

        $this->selectedSlug = $slug;

        return \sprintf('%s (%d) — Directed by %s', $movie->title, $movie->year ?? 0, $movie->director ?? 'Unknown');
    }

    /**
     * @return list<Movie>
     */
    public function getMovies(): array
    {
        if (null !== $this->results) {
            return $this->results;
        }

        $all = $this->movies->all();

        if ('' === $this->query) {
            return $this->results = $all;
        }

        $q = mb_strtolower($this->query);
        $this->results = array_values(array_filter($all, static function (Movie $movie) use ($q): bool {
            return str_contains(mb_strtolower($movie->title), $q)
                || str_contains(mb_strtolower($movie->director ?? ''), $q)
                || str_contains(mb_strtolower($movie->rawMarkdown), $q);
        }));

        return $this->results;
    }

    public function getSelectedMovie(): ?Movie
    {
        if (null === $this->selectedSlug) {
            return null;
        }

        return $this->movies->find($this->selectedSlug);
    }
}
