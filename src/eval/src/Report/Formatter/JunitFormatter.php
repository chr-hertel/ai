<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Report\Formatter;

use Symfony\AI\Eval\Report\GateResult;
use Symfony\AI\Eval\Report\Report;

/**
 * Renders one test suite per variant and one test case per eval case and repetition, understood by every CI system.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class JunitFormatter implements FormatterInterface
{
    public function format(Report $report): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElement('testsuites');
        self::attribute($root, 'name', $report->getSuite());
        $document->appendChild($root);

        $variants = $report->getVariants();
        foreach ($variants as $variant) {
            $gates = array_values(array_filter($report->getGateResults(), static fn (GateResult $gate): bool => $gate->getVariant() === $variant));
            $suite = $this->suite($document, $report->getSuite().'/'.$variant, \count($report->getResults($variant)) + \count($gates), \count($report->getFailures($variant)) + self::failedGates($gates));
            $root->appendChild($suite);

            foreach ($report->getResults($variant) as $result) {
                $case = $document->createElement('testcase');
                self::attribute($case, 'classname', $report->getSuite().'.'.$variant);
                self::attribute($case, 'name', $result->getCase()->getId().' #'.$result->getRepetition());
                $case->setAttribute('time', \sprintf('%.3f', $result->getRun()->getDuration()));

                if (!$result->isPassed()) {
                    $failure = $document->createElement('failure');
                    self::attribute($failure, 'message', implode(' ', $result->getFailureReasons()));
                    $case->appendChild($failure);
                }

                $suite->appendChild($case);
            }

            $this->appendGates($document, $suite, $report->getSuite().'.'.$variant.'.gates', $gates);
        }

        $otherGates = array_values(array_filter($report->getGateResults(), static fn (GateResult $gate): bool => !\in_array($gate->getVariant(), $variants, true)));
        if ([] !== $otherGates) {
            $suite = $this->suite($document, $report->getSuite().'/gates', \count($otherGates), self::failedGates($otherGates));
            $root->appendChild($suite);
            $this->appendGates($document, $suite, $report->getSuite().'.gates', $otherGates);
        }

        return (string) $document->saveXML();
    }

    private function suite(\DOMDocument $document, string $name, int $tests, int $failures): \DOMElement
    {
        $suite = $document->createElement('testsuite');
        self::attribute($suite, 'name', $name);
        $suite->setAttribute('tests', (string) $tests);
        $suite->setAttribute('failures', (string) $failures);

        return $suite;
    }

    /**
     * @param list<GateResult> $gates
     */
    private function appendGates(\DOMDocument $document, \DOMElement $suite, string $classname, array $gates): void
    {
        foreach ($gates as $gate) {
            $case = $document->createElement('testcase');
            self::attribute($case, 'classname', $classname);
            self::attribute($case, 'name', $gate->getGate());

            if (!$gate->isPassed()) {
                $failure = $document->createElement('failure');
                self::attribute($failure, 'message', $gate->getMessage());
                $case->appendChild($failure);
            }

            $suite->appendChild($case);
        }
    }

    /**
     * @param list<GateResult> $gates
     */
    private static function failedGates(array $gates): int
    {
        return \count(array_filter($gates, static fn (GateResult $gate): bool => !$gate->isPassed()));
    }

    private static function attribute(\DOMElement $element, string $name, string $value): void
    {
        // judge reasoning and exception messages may contain invalid UTF-8 or characters XML 1.0 does not allow
        $value = mb_scrub($value, 'UTF-8');
        $element->setAttribute($name, (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', "\u{FFFD}", $value));
    }
}
