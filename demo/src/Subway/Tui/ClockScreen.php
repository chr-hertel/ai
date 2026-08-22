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
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * The second screen: the countdown clock itself, redrawn every second so the minutes tick
 * down between two refreshes of the underlying feed.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ClockScreen
{
    private readonly TextWidget $board;
    private readonly TextWidget $back;
    private readonly ContainerWidget $container;

    /** @var list<Arrival> */
    private array $arrivals = [];

    private ?\DateTimeImmutable $fetchedAt = null;
    private ?string $error = null;

    public function __construct(
        private readonly Board $renderer,
        public readonly string $station,
    ) {
        $this->board = new TextWidget('', truncate: true);
        $this->back = new TextWidget('⌫ back');
        $this->back->addStyleClass('back');

        $this->container = new ContainerWidget();
        $this->container->addStyleClass('clock');
        $this->container->add($this->board);
        $this->container->add($this->back);
    }

    public function widget(): ContainerWidget
    {
        return $this->container;
    }

    /**
     * @param list<Arrival> $arrivals
     */
    public function update(array $arrivals, \DateTimeImmutable $fetchedAt): void
    {
        $this->arrivals = $arrivals;
        $this->fetchedAt = $fetchedAt;
        $this->error = null;
    }

    public function fail(string $message): void
    {
        $this->error = $message;
    }

    /**
     * Recomputes the board from the arrivals already in hand; no network involved, so this
     * can run once a second.
     */
    public function redraw(\DateTimeImmutable $now, int $height): void
    {
        if (null !== $this->error) {
            $this->board->setText(Palette::fg('#FF5252', Palette::center($this->error, Board::WIDTH)));

            return;
        }

        $this->board->setText($this->renderer->render($this->station, $this->arrivals, $now, $this->fetchedAt, $height));
    }

    /**
     * @return array<string, Style>
     */
    public static function styles(): array
    {
        return [
            // Constraining the width lets the root centre the whole board as one block.
            '.clock' => new Style(gap: 1, maxColumns: Board::WIDTH),
            '.back' => new Style(color: Palette::DIM, textAlign: TextAlign::Center),
        ];
    }
}
