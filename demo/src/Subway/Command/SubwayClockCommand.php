<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Subway\Command;

use App\Subway\Exception\SubwayException;
use App\Subway\Mta\SubwayClient;
use App\Subway\Tui\Board;
use App\Subway\Tui\ClockScreen;
use App\Subway\Tui\PickerScreen;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\KeyParser;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tui;

/**
 * A New York City subway countdown clock in the terminal, fed live by the public MCP
 * server at subwayinfo.nyc through the MCP Bundle's client support.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand('app:subway:clock', 'A live NYC subway countdown clock, driven by a remote MCP server.')]
final class SubwayClockCommand
{
    private const REFRESH_SECONDS = 20;

    private Tui $tui;
    private KeyParser $keys;
    private ?PickerScreen $picker = null;
    private ?ClockScreen $clock = null;
    private float $nextRefresh = 0.0;

    public function __construct(
        private readonly SubwayClient $client,
        private readonly Board $board,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        if (!stream_isatty(\STDOUT)) {
            $output->writeln('<error>This command needs an interactive terminal.</error>');

            return 1;
        }

        $this->keys = new KeyParser();
        $this->tui = new Tui(new StyleSheet([
            ':root' => new Style(align: Align::Center, verticalAlign: VerticalAlign::Center),
            ...PickerScreen::styles(),
            ...ClockScreen::styles(),
        ]));

        $this->tui->addListener($this->onKey(...), priority: 10);
        $this->tui->onTick($this->onTick(...));
        $this->showPicker();
        $this->tui->run();

        return 0;
    }

    private function showPicker(): void
    {
        $this->clock = null;
        $this->picker = new PickerScreen();

        $this->picker->list->onSelect(function (): void {
            $selected = $this->picker?->list->getSelectedItem();
            if (null !== $selected) {
                $this->showClock($selected['value']);
            }
        });

        $this->tui->clear()->add($this->picker->widget());
        $this->tui->setFocus($this->picker->input);
    }

    private function showClock(string $station): void
    {
        $this->picker = null;
        $this->clock = new ClockScreen($this->board, $station);

        $this->tui->clear()->add($this->clock->widget());
        $this->tui->setFocus(null);
        $this->nextRefresh = 0.0;
    }

    /**
     * The picker keeps the text input focused, so the keys the list needs are routed to it
     * here; everything else falls through to the input.
     *
     * Stopping propagation makes the TUI skip its own post-input render, so anything this
     * handler changes has to ask for one itself.
     */
    private function onKey(InputEvent $event): void
    {
        try {
            $this->route($event);
        } finally {
            $this->tui->requestRender();
        }
    }

    private function route(InputEvent $event): void
    {
        $data = $event->getData();

        if ($this->keys->matches($data, Key::ESCAPE) || "\x03" === $data) {
            $event->stopPropagation();

            if (null === $this->picker) {
                $this->showPicker();

                return;
            }

            $this->tui->stop();

            return;
        }

        // Nothing on the clock screen takes text, so backspace is free to mean "back";
        // enter activates the only element on it, which is the back button.
        if (null !== $this->clock) {
            if ($this->keys->matches($data, Key::BACKSPACE) || $this->keys->matches($data, Key::ENTER)) {
                $event->stopPropagation();
                $this->showPicker();
            }

            return;
        }

        if (null === $this->picker) {
            return;
        }

        if ($this->keys->matches($data, Key::UP) || $this->keys->matches($data, Key::DOWN)) {
            $event->stopPropagation();
            $this->picker->list->handleInput($data);

            return;
        }

        if (!$this->keys->matches($data, Key::ENTER)) {
            return;
        }

        $event->stopPropagation();
        $query = trim($this->picker->input->getValue());

        // An empty query means the rider picked one of the offered stations instead.
        if ('' === $query) {
            $this->picker->list->handleInput($data);

            return;
        }

        $this->search($query);
    }

    private function search(string $query): void
    {
        $picker = $this->picker;
        if (null === $picker) {
            return;
        }

        // Painted before the call: the search reaches over the network and blocks the loop.
        $picker->showSearching($query);
        $this->tui->requestRender();
        $this->tui->processRender();

        try {
            $stations = $this->client->searchStations($query);
        } catch (SubwayException $e) {
            $picker->showError($e->getMessage());

            return;
        }

        if (1 === \count($stations)) {
            $this->showClock($stations[0]->name);

            return;
        }

        // The query is cleared so the next Enter opens the highlighted result instead of
        // searching for the same thing again.
        $picker->input->setValue('');
        $picker->showResults($stations, $query);
    }

    /**
     * Redraws on every tick so the minutes count down, but only refetches from the MCP
     * server every {@see self::REFRESH_SECONDS}.
     */
    private function onTick(TickEvent $event): bool
    {
        if (null === $this->clock) {
            return false;
        }

        $now = new \DateTimeImmutable();

        if (microtime(true) >= $this->nextRefresh) {
            $this->nextRefresh = microtime(true) + self::REFRESH_SECONDS;

            try {
                $arrivals = $this->client->arrivals($this->clock->station);
                $this->clock->update($arrivals['arrivals'], $now);
            } catch (SubwayException $e) {
                $this->clock->fail($e->getMessage());
            }
        }

        $this->clock->redraw($now, $this->tui->getTerminal()->getRows());

        return false;
    }
}
