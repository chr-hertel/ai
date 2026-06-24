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

use Mcp\Capability\Attribute\McpTool;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;

/**
 * MCP App version of the movie browser.
 *
 * Like the web {@see MovieBrowser} Live Component, the markup is rendered by Twig — not built in
 * JavaScript. The tools return server-rendered HTML fragments (the "HTML-over-the-wire" pattern) that
 * the static iframe shell ({@see ../../templates/mcp/movies.html.twig}) drops into place:
 *
 *  - `search_movies` (the app's linked tool) returns the result grid ({@see ../../templates/mcp/_movies_grid.html.twig});
 *  - `show_movie` (an app-only follow-up tool) returns a movie's detail view ({@see ../../templates/mcp/_movie_detail.html.twig}).
 *
 * The iframe only wires events to `callTool(...)` and swaps the returned HTML in.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsMcpApp(
    uri: 'ui://movies',
    name: 'search_movies',
    title: 'Movies',
    description: 'Search the movie collection by title, director or cast and browse the results as an interactive grid.',
    template: 'mcp/movies.html.twig',
    prefersBorder: true,
)]
final class MovieApp
{
    public function __construct(
        private readonly MovieRepository $movies,
        private readonly McpAppRenderer $renderer,
    ) {
    }

    /**
     * Search movies and return the matching grid as a rendered HTML fragment.
     *
     * @param string $query search term matched against title, director and cast; empty returns all movies
     *
     * @return array{html: string, query: string, count: int}
     */
    public function render(string $query = ''): array
    {
        $movies = $this->search($query);

        return [
            'html' => $this->renderer->renderFragment('mcp/_movies_grid.html.twig', [
                'movies' => $movies,
                'query' => $query,
            ]),
            'query' => $query,
            'count' => \count($movies),
        ];
    }

    /**
     * Render a single movie's detail view as a rendered HTML fragment.
     *
     * @param string $slug the movie slug identifier
     *
     * @return array{html: string}
     */
    #[McpTool(name: 'show_movie', meta: ['ui' => ['resourceUri' => 'ui://movies', 'visibility' => ['app']]])]
    public function showMovie(string $slug): array
    {
        $movie = $this->movies->find($slug);

        return [
            'html' => $this->renderer->renderFragment('mcp/_movie_detail.html.twig', [
                'movie' => $movie,
            ]),
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
            if (str_contains(mb_strtolower($movie->title), $needle)) {
                return true;
            }

            if (str_contains(mb_strtolower($movie->director ?? ''), $needle)) {
                return true;
            }

            foreach ($movie->cast as $member) {
                if (str_contains(mb_strtolower($member['name']), $needle)) {
                    return true;
                }

                if (str_contains(mb_strtolower($member['role']), $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }
}
