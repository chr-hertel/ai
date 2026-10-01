<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Eval\Dataset;
use Symfony\AI\Eval\Exception\InvalidArgumentException;

final class DatasetTest extends TestCase
{
    public function testLoadsCasesFromYaml()
    {
        $dataset = Dataset::fromFile(__DIR__.'/Fixtures/datasets/support.yaml');

        $this->assertSame('support-regressions', $dataset->getName());
        $this->assertCount(2, $dataset);

        $case = $dataset->getCases()[0];
        $this->assertSame('refund-without-ticket-001', $case->getId());
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $case->getSourceRun());
        $this->assertTrue($case->hasLabel('refund'));
        $this->assertSame('strict', $case->expectation('tool_order'));
        $this->assertSame('SO-10023', $case->expectation('tool_arguments.open_refund.orderNumber'));
        $this->assertNull($case->expectation('judge.criteria'));
        $this->assertSame('I want my money back for SO-10023, the jacket is too small.', $case->getMessages()->getUserMessage()?->asText());
    }

    public function testRoundTrip()
    {
        $dataset = Dataset::fromFile(__DIR__.'/Fixtures/datasets/support.yaml');

        $this->assertEquals($dataset, Dataset::fromArray($dataset->toArray()));
    }

    public function testCaseWithoutInputIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);

        Dataset::fromArray(['name' => 'broken', 'cases' => [['id' => 'no-input']]]);
    }
}
