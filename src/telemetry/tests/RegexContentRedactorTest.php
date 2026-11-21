<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Telemetry\Exception\InvalidArgumentException;
use Symfony\AI\Telemetry\RegexContentRedactor;

final class RegexContentRedactorTest extends TestCase
{
    #[DataProvider('provideContent')]
    public function testDefaultPatterns(string $content, string $expected)
    {
        $this->assertSame($expected, (new RegexContentRedactor())->redact($content));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideContent(): iterable
    {
        yield 'email' => ['Mail jane.doe+shop@example.co.uk!', 'Mail [email]!'];
        yield 'card with spaces' => ['Card 4111 1111 1111 1111 please', 'Card [card] please'];
        yield 'card in JSON' => ['{"card":"4242424242424242"}', '{"card":"[card]"}'];
        yield 'timestamp' => ['At 1730000000000 ms', 'At 1730000000000 ms'];
        yield 'order number' => ['Order 2024110512345678', 'Order 2024110512345678'];
    }

    public function testCallbackReplacesMatches()
    {
        $redactor = new RegexContentRedactor(['/SO-\d+/' => static fn (string $match): string => substr($match, 0, 3).'***']);

        $this->assertSame('Order SO-*** shipped', $redactor->redact('Order SO-10023 shipped'));
    }

    public function testContentIsDroppedIfItCannotBeRedacted()
    {
        $redactor = new RegexContentRedactor(['/secret/u' => '[secret]']);

        $this->assertSame(RegexContentRedactor::FAILED, $redactor->redact("secret \xff"));
    }

    public function testInvalidPatternIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);

        new RegexContentRedactor(['/(unclosed' => '[x]']);
    }
}
