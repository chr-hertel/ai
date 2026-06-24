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

use Symfony\AI\McpBundle\Attribute\AsMcpApp;

/**
 * MCP App version of the movie browser.
 *
 * Unlike the web {@see MovieBrowser} Live Component, this is a plain class: the linked tool
 * (`search_movies`) returns the matching movies as a structured model, and the static iframe shell
 * ({@see ../../templates/mcp/movies.html.twig}) renders it client-side and re-searches via `callTool`.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsMcpApp(
    uri: 'ui://movies',
    name: 'search_movies',
    title: 'Movies',
    description: 'Search the movie collection by title, director or content and browse the results as an interactive grid.',
    template: 'mcp/movies.html.twig',
    prefersBorder: true,
)]
final class MovieApp
{
    public function __construct(
        private readonly MovieRepository $movies,
    ) {
    }

    /**
     * Search movies and return the matches as the app model.
     *
     * @param string $query search term matched against title, director and content; empty returns all movies
     *
     * @return array{
     *     query: string,
     *     count: int,
     *     movies: list<array{
     *         slug: string,
     *         title: string,
     *         year: int|null,
     *         director: string|null,
     *         imdb: string|null,
     *         hue: int,
     *         cast: list<array{name: string, role: string}>,
     *         plot: list<string>,
     *     }>,
     * }
     */
    public function render(string $query = ''): array
    {
        $movies = $this->search($query);

        return [
            'query' => $query,
            'count' => \count($movies),
            'movies' => array_map(static fn (Movie $movie): array => [
                'slug' => $movie->slug,
                'title' => $movie->title,
                'year' => $movie->year,
                'director' => $movie->director,
                'imdb' => $movie->imdb,
                'hue' => $movie->hue(),
                'cast' => $movie->cast,
                'plot' => $movie->plot,
            ], $movies),
        ];
    }

    /**
     * @return list<Movie>
     */
    private function search(string $query): array
    {
        $all = $this->movies->all();

        if ('' === $query) {
            return $all;
        }

        $needle = mb_strtolower($query);

        return array_values(array_filter($all, static function (Movie $movie) use ($needle): bool {
            return str_contains(mb_strtolower($movie->title), $needle)
                || str_contains(mb_strtolower($movie->director ?? ''), $needle)
                || str_contains(mb_strtolower($movie->rawMarkdown), $needle);
        }));
    }
}
