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
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Style\Color;

/**
 * The look of a platform countdown clock: amber on black, with the line bullets in the
 * colors the MTA assigns to each trunk.
 *
 * The TUI text wrapper measures ANSI-aware, so raw escape sequences can be mixed into a
 * TextWidget to color individual spans of a row.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Palette
{
    public const AMBER = '#FFB300';
    public const DIM = '#8A6510';
    public const BLACK = '#000000';
    public const WHITE = '#FFFFFF';

    private const RESET = "\x1b[0m";

    /**
     * A line bullet: the letter or number in a disc of the trunk's color.
     */
    public static function bullet(Line $line): string
    {
        $letter = $line->hasDarkLetter() ? self::BLACK : self::WHITE;

        return Color::hex($line->color)->toBackgroundCode()
            .Color::hex($letter)->toForegroundCode()
            ." {$line->label} "
            .self::RESET;
    }

    public static function fg(string $hex, string $text): string
    {
        return Color::hex($hex)->toForegroundCode().$text.self::RESET;
    }

    /**
     * Renders a row the way an arriving train is announced on the real clocks: the cell
     * flashes between black-on-amber and amber-on-black once a second.
     */
    public static function flash(string $text, bool $inverted): string
    {
        if (!$inverted) {
            return $text;
        }

        return Color::hex(self::AMBER)->toBackgroundCode()
            .Color::hex(self::BLACK)->toForegroundCode()
            .AnsiUtils::stripAnsiCodes($text)
            .self::RESET;
    }

    /**
     * Pads a row to the board width, ignoring the width of any escape sequences in it.
     */
    public static function pad(string $text, int $width): string
    {
        return $text.str_repeat(' ', max(0, $width - AnsiUtils::visibleWidth($text)));
    }

    public static function center(string $text, int $width): string
    {
        $left = max(0, intdiv($width - AnsiUtils::visibleWidth($text), 2));

        return self::pad(str_repeat(' ', $left).$text, $width);
    }
}
