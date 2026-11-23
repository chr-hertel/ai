<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry;

use Symfony\AI\Telemetry\Exception\InvalidArgumentException;

/**
 * Replaces every match of the configured patterns by its placeholder, or by the result of its callback.
 *
 * Content that cannot be redacted, e.g. because of a PCRE error, is replaced as a whole by {@see self::FAILED}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RegexContentRedactor implements ContentRedactorInterface
{
    public const EMAIL = '/[\w.+-]+@[\w-]+\.[\w.-]+/';
    public const CARD_NUMBER = '/(?<!\d)\d(?:[ -]?\d){12,18}(?!\d)/';
    public const FAILED = '[redaction failed]';

    /**
     * @var array<string, string|\Closure(string): string>
     */
    private readonly array $patterns;

    /**
     * @param array<string, string|\Closure(string): string>|null $patterns placeholders, or callbacks returning the replacement of a match, keyed by regular expression; email addresses and card numbers if null
     */
    public function __construct(?array $patterns = null)
    {
        $patterns ??= [self::EMAIL => '[email]', self::CARD_NUMBER => self::redactCardNumber(...)];

        foreach (array_keys($patterns) as $pattern) {
            if (false === @preg_match($pattern, '')) {
                throw new InvalidArgumentException(\sprintf('The redaction pattern "%s" is not a valid regular expression.', $pattern));
            }
        }

        $this->patterns = $patterns;
    }

    public function redact(string $content): string
    {
        foreach ($this->patterns as $pattern => $replacement) {
            if ($replacement instanceof \Closure) {
                $content = preg_replace_callback($pattern, static fn (array $match): string => $replacement($match[0]), $content);
            } else {
                $content = preg_replace($pattern, $replacement, $content);
            }

            if (null === $content) {
                return self::FAILED;
            }
        }

        return $content;
    }

    /**
     * Only digit runs passing the Luhn check are card numbers, not timestamps or order numbers.
     */
    private static function redactCardNumber(string $match): string
    {
        $sum = 0;
        foreach (str_split(strrev(preg_replace('/\D/', '', $match) ?? '')) as $position => $digit) {
            $value = (int) $digit;
            if (1 === $position % 2) {
                $value *= 2;
            }

            $sum += $value > 9 ? $value - 9 : $value;
        }

        if (0 !== $sum % 10) {
            return $match;
        }

        return '[card]';
    }
}
