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
 * A subway line and the trunk it belongs to.
 *
 * Colors are the official ones the MTA publishes in colors.csv. Lines sharing a color
 * share a trunk, which is also how a station groups its platforms: the board at Times Sq
 * has one panel for N/Q/R/W, one for 1/2/3 and one for 7.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class Line
{
    /**
     * @var array<string, array{trunk: string, color: string}>
     */
    private const TRUNKS = [
        '1' => ['trunk' => '1 2 3', 'color' => 'EE352E'],
        '2' => ['trunk' => '1 2 3', 'color' => 'EE352E'],
        '3' => ['trunk' => '1 2 3', 'color' => 'EE352E'],
        '4' => ['trunk' => '4 5 6', 'color' => '00933C'],
        '5' => ['trunk' => '4 5 6', 'color' => '00933C'],
        '6' => ['trunk' => '4 5 6', 'color' => '00933C'],
        '7' => ['trunk' => '7', 'color' => 'B933AD'],
        'A' => ['trunk' => 'A C E', 'color' => '2850AD'],
        'C' => ['trunk' => 'A C E', 'color' => '2850AD'],
        'E' => ['trunk' => 'A C E', 'color' => '2850AD'],
        'B' => ['trunk' => 'B D F M', 'color' => 'FF6319'],
        'D' => ['trunk' => 'B D F M', 'color' => 'FF6319'],
        'F' => ['trunk' => 'B D F M', 'color' => 'FF6319'],
        'M' => ['trunk' => 'B D F M', 'color' => 'FF6319'],
        'G' => ['trunk' => 'G', 'color' => '6CBE45'],
        'J' => ['trunk' => 'J Z', 'color' => '996633'],
        'Z' => ['trunk' => 'J Z', 'color' => '996633'],
        'L' => ['trunk' => 'L', 'color' => 'A7A9AC'],
        'N' => ['trunk' => 'N Q R W', 'color' => 'FCCC0A'],
        'Q' => ['trunk' => 'N Q R W', 'color' => 'FCCC0A'],
        'R' => ['trunk' => 'N Q R W', 'color' => 'FCCC0A'],
        'W' => ['trunk' => 'N Q R W', 'color' => 'FCCC0A'],
        'S' => ['trunk' => 'S', 'color' => '808183'],
        'SIR' => ['trunk' => 'SIR', 'color' => '0078C6'],
    ];

    /**
     * @var list<string>
     */
    private const TRUNK_ORDER = ['1 2 3', '4 5 6', '7', 'A C E', 'B D F M', 'G', 'J Z', 'L', 'N Q R W', 'S', 'SIR'];

    /**
     * The yellow and grey bullets carry a black letter, every other bullet a white one.
     *
     * @var list<string>
     */
    private const DARK_LETTER_TRUNKS = ['N Q R W'];

    private function __construct(
        public string $name,
        public string $label,
        public string $trunk,
        public string $color,
    ) {
    }

    public static function from(string $name): self
    {
        $name = strtoupper(trim($name));
        $label = $name;

        // Express variants such as "6X" or "7X" ride the same trunk as their local.
        $trunk = self::TRUNKS[$name] ?? self::TRUNKS[rtrim($name, 'X')] ?? null;

        if (null === $trunk) {
            // Shuttles reach the feed as "GS", "FS" or "H"; the bullet just reads "S".
            $trunk = str_ends_with($name, 'S') ? self::TRUNKS['S'] : ['trunk' => '?', 'color' => '808183'];
            $label = str_ends_with($name, 'S') ? 'S' : $name;
        }

        return new self($name, $label, $trunk['trunk'], $trunk['color']);
    }

    /**
     * Panels keep a fixed order on the board so they do not jump around between refreshes.
     */
    public function trunkOrder(): int
    {
        $position = array_search($this->trunk, self::TRUNK_ORDER, true);

        return false === $position ? \count(self::TRUNK_ORDER) : $position;
    }

    public function hasDarkLetter(): bool
    {
        return \in_array($this->trunk, self::DARK_LETTER_TRUNKS, true);
    }
}
