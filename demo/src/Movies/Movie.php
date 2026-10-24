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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\IndexableEntityInterface;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[ORM\Entity(repositoryClass: MovieRepository::class)]
#[ORM\Index(name: 'movie_embedding_idx', columns: ['embedding'])]
class Movie implements IndexableEntityInterface
{
    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['title', 'director', 'cast', 'plot'])]
    private ?VectorInterface $embedding = null;

    /**
     * @param list<array{name: string, role: string}> $cast
     * @param list<string>                            $plot
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 100)]
        public readonly string $slug,
        #[ORM\Column]
        public readonly string $title,
        #[ORM\Column(nullable: true)]
        public readonly ?int $year,
        #[ORM\Column(nullable: true)]
        public readonly ?string $director,
        #[ORM\Column(nullable: true)]
        public readonly ?string $imdb,
        #[ORM\Column(name: 'cast_members', type: Types::JSON)]
        public readonly array $cast,
        #[ORM\Column(type: Types::JSON)]
        public readonly array $plot,
    ) {
    }

    public static function fromFile(string $path): self
    {
        $slug = basename($path, '.md');
        $content = file_get_contents($path);
        $title = $slug;
        $year = null;
        if (preg_match('/^#\s+(.+?)(?:\s*\((\d{4})\))?\s*$/m', (string) $content, $matches)) {
            $title = trim($matches[1]);
            $year = isset($matches[2]) ? (int) $matches[2] : null;
        }

        $director = null;
        if (preg_match('/^\*\*Director:\*\*\s*(.+?)\s*$/m', (string) $content, $matches)) {
            $director = trim($matches[1]);
        }

        $imdb = null;
        if (preg_match('/^\*\*IMDB\*\*:\s*(\S+)/m', (string) $content, $matches)) {
            $imdb = trim($matches[1]);
        }

        $cast = [];
        if (preg_match('/^##\s+Cast\s*$(.*?)(?=^##\s+|\z)/ms', (string) $content, $matches)) {
            preg_match_all('/^-\s*\*\*(.+?)\*\*\s+as\s+(.+?)\s*$/m', $matches[1], $castMatches, \PREG_SET_ORDER);
            foreach ($castMatches as $castMatch) {
                $cast[] = [
                    'name' => trim($castMatch[1]),
                    'role' => trim($castMatch[2]),
                ];
            }
        }

        $plot = [];
        if (preg_match('/^##\s+Plot\s*$(.*?)(?=^##\s+|\z)/ms', (string) $content, $matches)) {
            foreach (preg_split('/\n{2,}/', trim($matches[1])) ?: [] as $paragraph) {
                $paragraph = trim($paragraph);
                if ('' !== $paragraph) {
                    $plot[] = $paragraph;
                }
            }
        }

        return new self($slug, $title, $year, $director, $imdb, $cast, $plot);
    }

    public function hue(): int
    {
        return crc32($this->slug) % 360;
    }

    public function getIndexableContent(): string
    {
        $lines = [null === $this->year ? $this->title : \sprintf('%s (%d)', $this->title, $this->year)];

        if (null !== $this->director) {
            $lines[] = 'Directed by '.$this->director;
        }

        if ([] !== $this->cast) {
            $lines[] = 'Starring '.implode(', ', array_map(static fn (array $member): string => \sprintf('%s as %s', $member['name'], $member['role']), $this->cast));
        }

        return implode(\PHP_EOL, [...$lines, ...$this->plot]);
    }
}
