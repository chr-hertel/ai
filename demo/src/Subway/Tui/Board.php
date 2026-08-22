<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Subway\Tui;

use App\Subway\Mta\Arrival;
use Symfony\Component\Tui\Ansi\AnsiUtils;

/**
 * Lays out the arrivals the way a platform countdown clock does: one panel per trunk,
 * trains numbered in the order they reach the platform, minutes flush right.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Board
{
    public const WIDTH = 54;

    private const MAX_ROWS_PER_PANEL = 4;
    private const MIN_ROWS_PER_PANEL = 1;
    private const COUNTDOWN_WIDTH = 6;
    private const DEPARTED_GRACE_SECONDS = 20;

    /**
     * @param list<Arrival> $arrivals
     * @param int           $height   Lines the board may occupy, so a short terminal drops
     *                                rows instead of scrolling the panels out of view
     */
    public function render(string $station, array $arrivals, \DateTimeImmutable $now, ?\DateTimeImmutable $fetchedAt, int $height = 40): string
    {
        $panels = $this->panels($arrivals, $now);
        $rowsPerPanel = $this->rowsPerPanel(\count($panels), $height);

        $lines = [
            Palette::fg(Palette::AMBER, Palette::center(mb_strtoupper($station), self::WIDTH)),
            Palette::fg(Palette::DIM, str_repeat('─', self::WIDTH)),
            '',
        ];

        if ([] === $panels) {
            $lines[] = Palette::fg(Palette::DIM, Palette::center('no trains scheduled', self::WIDTH));
            $lines[] = '';
        }

        foreach ($panels as $trunk => $trains) {
            $lines[] = Palette::fg(Palette::DIM, '  '.$trunk);

            foreach (\array_slice($trains, 0, $rowsPerPanel) as $position => $train) {
                $lines[] = $this->renderRow($position + 1, $train, $now);
            }

            $lines[] = '';
        }

        $lines[] = Palette::fg(Palette::DIM, str_repeat('─', self::WIDTH));
        $lines[] = $this->renderStatus($now, $fetchedAt);

        return implode("\n", $lines);
    }

    private function renderRow(int $position, Arrival $arrival, \DateTimeImmutable $now): string
    {
        $minutes = $arrival->minutesAway($now);
        $countdown = \sprintf('%'.self::COUNTDOWN_WIDTH.'s', 0 === $minutes ? 'now' : \sprintf('%d min', $minutes));

        $left = \sprintf('   %d.  %s  ', $position, Palette::bullet($arrival->line));
        $headsign = $this->fit($arrival->headsign, self::WIDTH - AnsiUtils::visibleWidth($left) - self::COUNTDOWN_WIDTH - 2);

        $row = Palette::pad($left.$headsign, self::WIDTH - self::COUNTDOWN_WIDTH - 2).$countdown.'  ';

        // The real clocks flash an arriving train between black and amber once a second.
        if ($arrival->isArriving($now)) {
            return Palette::flash($row, 0 === $now->getTimestamp() % 2);
        }

        return Palette::fg(Palette::AMBER, $row);
    }

    private function renderStatus(\DateTimeImmutable $now, ?\DateTimeImmutable $fetchedAt): string
    {
        $clock = $now->format('g:i:s A');
        $age = null === $fetchedAt
            ? 'connecting…'
            : \sprintf('updated %ds ago', max(0, $now->getTimestamp() - $fetchedAt->getTimestamp()));

        return Palette::fg(Palette::DIM, '  '.Palette::pad($clock, self::WIDTH - \strlen($age) - 4).$age);
    }

    /**
     * Groups the arrivals into one panel per trunk, mirroring how a station splits its
     * platforms, and keeps both the panels and the trains inside them in a stable order.
     *
     * Trains that already left are dropped, so a feed that goes stale between two
     * refreshes empties the panel instead of showing every train as arriving forever.
     *
     * @param list<Arrival> $arrivals
     *
     * @return array<string, list<Arrival>>
     */
    private function panels(array $arrivals, \DateTimeImmutable $now): array
    {
        $departed = $now->getTimestamp() - self::DEPARTED_GRACE_SECONDS;
        $arrivals = array_filter($arrivals, static fn (Arrival $a): bool => $a->arrivesAt->getTimestamp() >= $departed);

        usort($arrivals, static function (Arrival $a, Arrival $b): int {
            return [$a->line->trunkOrder(), $a->arrivesAt] <=> [$b->line->trunkOrder(), $b->arrivesAt];
        });

        $panels = [];
        foreach ($arrivals as $arrival) {
            $panels[$arrival->line->trunk][] = $arrival;
        }

        return $panels;
    }

    /**
     * Each panel costs its header, its rows and a blank line; the frame around them costs
     * five more.
     */
    private function rowsPerPanel(int $panelCount, int $height): int
    {
        if (0 === $panelCount) {
            return self::MAX_ROWS_PER_PANEL;
        }

        $available = intdiv(max(0, $height - 5), $panelCount) - 2;

        return max(self::MIN_ROWS_PER_PANEL, min(self::MAX_ROWS_PER_PANEL, $available));
    }

    private function fit(string $text, int $width): string
    {
        if (mb_strlen($text) <= $width) {
            return $text;
        }

        return mb_substr($text, 0, max(0, $width - 1)).'…';
    }
}
