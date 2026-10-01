<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge;

/**
 * Replaces every match of the configured patterns by its placeholder.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RegexContentRedactor implements ContentRedactorInterface
{
    public const EMAIL = '/[\w.+-]+@[\w-]+\.[\w.-]+/';
    public const CARD_NUMBER = '/\b(?:\d[ -]?){13,19}\b/';

    /**
     * @param array<string, string> $patterns placeholders keyed by regular expression
     */
    public function __construct(
        private readonly array $patterns = [self::EMAIL => '[email]', self::CARD_NUMBER => '[card]'],
    ) {
    }

    public function redact(string $content): string
    {
        return preg_replace(array_keys($this->patterns), array_values($this->patterns), $content) ?? $content;
    }
}
