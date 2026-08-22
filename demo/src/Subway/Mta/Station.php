<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Subway\Mta;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class Station
{
    /**
     * @param list<string> $lines
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $borough,
        public array $lines,
    ) {
    }

    /**
     * @param array{id: string, name: string, borough?: string|null, lines?: list<string>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['name'],
            $data['borough'] ?? '',
            array_values($data['lines'] ?? []),
        );
    }
}
