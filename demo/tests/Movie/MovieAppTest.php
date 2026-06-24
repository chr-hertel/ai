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

use App\Movies\MovieApp;
use App\Movies\MovieRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(MovieApp::class)]
final class MovieAppTest extends KernelTestCase
{
    public function testRenderReturnsGridHtmlForAllMovies(): void
    {
        $result = $this->app()->render('');

        $this->assertArrayHasKey('html', $result);
        $this->assertGreaterThan(0, $result['count']);
        $this->assertStringContainsString('class="grid"', $result['html']);
        $this->assertStringContainsString('Oppenheimer', $result['html']);
        $this->assertStringContainsString('Forrest Gump', $result['html']);
    }

    public function testRenderNarrowsByDirector(): void
    {
        $result = $this->app()->render('Tarantino');

        $this->assertGreaterThan(0, $result['count']);
        $this->assertStringContainsString('Django Unchained', $result['html']);
        $this->assertStringNotContainsString('Oppenheimer', $result['html']);
    }

    public function testRenderNarrowsByCast(): void
    {
        $result = $this->app()->render('Cillian'); // Cillian Murphy plays in Oppenheimer

        $this->assertStringContainsString('Oppenheimer', $result['html']);
        $this->assertStringNotContainsString('Forrest Gump', $result['html']);
    }

    public function testShowMovieRendersMarkdownPlot(): void
    {
        $html = $this->app()->showMovie('oppenheimer')['html'];

        $this->assertStringContainsString('Christopher Nolan', $html);
        $this->assertStringContainsString('<h2>Plot</h2>', $html);
        $this->assertStringContainsString('<p>', $html); // plot markdown rendered to HTML paragraphs
    }

    public function testShowMovieHandlesUnknownSlug(): void
    {
        $html = $this->app()->showMovie('does-not-exist')['html'];

        $this->assertStringContainsString('Movie not found', $html);
    }

    private function app(): MovieApp
    {
        self::bootKernel();
        $container = self::getContainer();

        // Build from real services so the Twig fragments (incl. markdown_to_html) render end-to-end.
        $movies = $container->get(MovieRepository::class);
        $renderer = $container->get(McpAppRenderer::class);
        \assert($movies instanceof MovieRepository);
        \assert($renderer instanceof McpAppRenderer);

        return new MovieApp($movies, $renderer);
    }
}
