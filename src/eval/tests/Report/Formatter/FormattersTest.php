<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Report\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\Report\CaseResult;
use Symfony\AI\Eval\Report\Formatter\JsonFormatter;
use Symfony\AI\Eval\Report\Formatter\JunitFormatter;
use Symfony\AI\Eval\Report\Formatter\MarkdownFormatter;
use Symfony\AI\Eval\Report\Formatter\TextFormatter;
use Symfony\AI\Eval\Report\GateResult;
use Symfony\AI\Eval\Report\Report;
use Symfony\AI\Eval\Score;
use Symfony\AI\Platform\Cost\Cost;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

final class FormattersTest extends TestCase
{
    public function testJunitIsValidXmlWithControlCharactersAndInvalidUtf8()
    {
        $document = new \DOMDocument();

        $this->assertTrue($document->loadXML((new JunitFormatter())->format($this->report())));
        $this->assertStringContainsString('the answer', (string) $document->getElementsByTagName('failure')->item(0)?->getAttribute('message'));
    }

    public function testJunitCountsIncludeGates()
    {
        $junit = simplexml_load_string((new JunitFormatter())->format($this->report()));
        $this->assertNotFalse($junit);

        $this->assertSame('3', (string) $junit->testsuite[0]['tests']);
        $this->assertSame('2', (string) $junit->testsuite[0]['failures']);
        $this->assertCount(3, $junit->testsuite[0]->testcase);
    }

    public function testJunitKeepsGatesWithoutVariantOfTheRun()
    {
        $report = $this->report();
        $report->addGateResults(new GateResult('pass_rate', 'cheap', false, 'none of the variants "cheap" was run'));

        $junit = simplexml_load_string((new JunitFormatter())->format($report));
        $this->assertNotFalse($junit);

        $this->assertSame('suite/gates', (string) $junit->testsuite[1]['name']);
        $this->assertSame('1', (string) $junit->testsuite[1]['failures']);
    }

    public function testJsonSubstitutesInvalidUtf8()
    {
        $json = json_decode((new JsonFormatter())->format($this->report()), true);

        $this->assertIsArray($json);
        $this->assertSame("Hello \u{FFFD}", $json['results'][0]['answer']);
        $this->assertSame(0.25, $json['variants']['a|b']['average_cost']);
        $this->assertSame('EUR', $json['variants']['a|b']['currency']);
    }

    public function testMarkdownEscapesTableCellsAndShowsCurrency()
    {
        $markdown = (new MarkdownFormatter())->format($this->report());

        $this->assertStringContainsString('| a\|b | 0.0 % | 0.0 % | 0.2500 EUR |', $markdown);
        $this->assertStringContainsString('| Variant | Pass rate | on\|off | Avg cost |', $markdown);
        $this->assertStringNotContainsString('$', $markdown);
    }

    public function testTextShowsCurrency()
    {
        $text = (new TextFormatter())->format($this->report());

        $this->assertStringContainsString('0.2500 EUR', $text);
        $this->assertStringNotContainsString('$', $text);
    }

    private function report(): Report
    {
        $run = new RecordedRun(new MessageBag(Message::ofUser('Hi')), new TextResult("Hello \xB1"));
        $case = new EvalCase('greeting', 'Hi', ['on|off']);
        $score = Score::fail('judge', "the answer \x01is \xC3\x28 wrong");

        $report = new Report('suite', [new CaseResult('a|b', $case, 1, $run, [$score], new Cost(0.25, 'EUR'))]);
        $report->addGateResults(
            new GateResult('pass_rate', 'a|b', false, "pass rate \x07 0 %"),
            new GateResult('cost_per_case', 'a|b', true, 'cost per case 0.2500 EUR <= 1.0000 EUR'),
        );

        return $report;
    }
}
