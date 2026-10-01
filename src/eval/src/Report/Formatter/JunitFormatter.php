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
        $root->setAttribute('name', $report->getSuite());
        $document->appendChild($root);

        foreach ($report->getVariants() as $variant) {
            $results = $report->getResults($variant);
            $suite = $document->createElement('testsuite');
            $suite->setAttribute('name', $report->getSuite().'/'.$variant);
            $suite->setAttribute('tests', (string) \count($results));
            $suite->setAttribute('failures', (string) \count($report->getFailures($variant)));
            $root->appendChild($suite);

            foreach ($results as $result) {
                $case = $document->createElement('testcase');
                $case->setAttribute('classname', $report->getSuite().'.'.$variant);
                $case->setAttribute('name', $result->getCase()->getId().' #'.$result->getRepetition());
                $case->setAttribute('time', \sprintf('%.3f', $result->getRun()->getDuration()));

                if (!$result->isPassed()) {
                    $failure = $document->createElement('failure');
                    $failure->setAttribute('message', implode(' ', $result->getFailureReasons()));
                    $case->appendChild($failure);
                }

                $suite->appendChild($case);
            }

            foreach ($report->getGateResults() as $gate) {
                if ($gate->getVariant() !== $variant) {
                    continue;
                }

                $case = $document->createElement('testcase');
                $case->setAttribute('classname', $report->getSuite().'.'.$variant.'.gates');
                $case->setAttribute('name', $gate->getGate());

                if (!$gate->isPassed()) {
                    $failure = $document->createElement('failure');
                    $failure->setAttribute('message', $gate->getMessage());
                    $case->appendChild($failure);
                }

                $suite->appendChild($case);
            }
        }

        return (string) $document->saveXML();
    }
}
