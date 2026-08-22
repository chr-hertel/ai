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

use App\Subway\Mta\Line;
use App\Subway\Mta\Station;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * The first screen: which station are you standing at?
 *
 * A handful of well-known complexes are offered right away; anything else is found by
 * typing and letting the remote MCP server search for it.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PickerScreen
{
    /**
     * Stations riders are most likely to be standing at, so the list is useful before a
     * single key is pressed.
     *
     * @var list<array{name: string, lines: list<string>, borough: string}>
     */
    private const SUGGESTIONS = [
        ['name' => 'Times Sq-42 St', 'lines' => ['1', '2', '3', '7', 'N', 'Q', 'R', 'W', 'S'], 'borough' => 'Manhattan'],
        ['name' => 'Grand Central-42 St', 'lines' => ['4', '5', '6', '7', 'S'], 'borough' => 'Manhattan'],
        ['name' => '14 St-Union Sq', 'lines' => ['4', '5', '6', 'L', 'N', 'Q', 'R', 'W'], 'borough' => 'Manhattan'],
        ['name' => 'Atlantic Av-Barclays Ctr', 'lines' => ['2', '3', '4', '5', 'B', 'D', 'N', 'Q', 'R', 'W'], 'borough' => 'Brooklyn'],
        ['name' => 'Coney Island-Stillwell Av', 'lines' => ['D', 'F', 'N', 'Q'], 'borough' => 'Brooklyn'],
        ['name' => '161 St-Yankee Stadium', 'lines' => ['4', 'B', 'D'], 'borough' => 'Bronx'],
        ['name' => 'Jackson Hts-Roosevelt Av', 'lines' => ['E', 'F', 'M', 'R'], 'borough' => 'Queens'],
    ];

    private const NAME_WIDTH = 24;
    private const MAX_BULLETS = 8;

    public readonly InputWidget $input;
    public readonly SelectListWidget $list;

    private readonly TextWidget $status;
    private readonly ContainerWidget $container;

    public function __construct()
    {
        $this->input = new InputWidget();
        $this->input->setPrompt('  ▸ ');

        $this->list = new SelectListWidget($this->items($this->suggestedStations()), maxVisible: 7);
        $this->list->addStyleClass('stations');

        $this->status = new TextWidget($this->hint());

        $this->container = new ContainerWidget();
        $this->container->addStyleClass('picker');
        $this->container->add((new TextWidget("MTA  ·  SUBWAY\nWHERE ARE YOU?"))->addStyleClass('marquee'));
        $this->container->add($this->input);
        $this->container->add($this->list);
        $this->container->add($this->status);
    }

    public function widget(): ContainerWidget
    {
        return $this->container;
    }

    /**
     * @param list<Station> $stations
     */
    public function showResults(array $stations, string $query): void
    {
        if ([] === $stations) {
            $this->status->setText(Palette::fg(Palette::DIM, \sprintf('  no station matches "%s"', $query)));

            return;
        }

        $this->list->setItems($this->items($stations));
        $this->list->setSelectedIndex(0);
        $this->status->setText($this->hint());
    }

    public function showSearching(string $query): void
    {
        $this->status->setText(Palette::fg(Palette::DIM, \sprintf('  searching for "%s" …', $query)));
    }

    public function showError(string $message): void
    {
        $this->status->setText(Palette::fg('#FF5252', '  '.$message));
    }

    /**
     * @return array<string, Style>
     */
    public static function styles(): array
    {
        return [
            '.picker' => new Style(gap: 1, maxColumns: Board::WIDTH + 4),
            '.marquee' => new Style(color: Palette::AMBER, bold: true, textAlign: TextAlign::Center),
            '.stations' => new Style(color: Palette::AMBER),
        ];
    }

    /**
     * @return list<Station>
     */
    private function suggestedStations(): array
    {
        return array_map(
            static fn (array $s): Station => new Station($s['name'], $s['name'], $s['borough'], $s['lines']),
            self::SUGGESTIONS,
        );
    }

    /**
     * The value is the station name, not its id: asking the server for arrivals by name
     * covers every platform of a complex instead of a single one.
     *
     * @param list<Station> $stations
     *
     * @return list<array{value: string, label: string}>
     */
    private function items(array $stations): array
    {
        return array_map(static function (Station $station): array {
            // The list truncates without understanding escape sequences, so the row is kept
            // short enough that a bullet is never cut in half.
            $lines = \array_slice($station->lines, 0, self::MAX_BULLETS);
            $bullets = implode('', array_map(
                static fn (string $line): string => Palette::bullet(Line::from($line)),
                $lines,
            ));

            return [
                'value' => $station->name,
                'label' => Palette::pad(self::fit($station->name, self::NAME_WIDTH), self::NAME_WIDTH + 1)
                    .$bullets
                    .(\count($station->lines) > self::MAX_BULLETS ? '…' : ''),
            ];
        }, $stations);
    }

    private static function fit(string $text, int $width): string
    {
        return mb_strlen($text) <= $width ? $text : mb_substr($text, 0, $width - 1).'…';
    }

    private function hint(): string
    {
        return Palette::fg(Palette::DIM, '  ↑↓ choose · ⏎ open the board · type to search · esc quit');
    }
}
