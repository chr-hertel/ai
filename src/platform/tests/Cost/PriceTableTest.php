<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Cost;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Cost\PriceTable;

final class PriceTableTest extends TestCase
{
    public function testExactModelIsPriced()
    {
        $table = PriceTable::fromArray(['gpt-4o' => ['input' => 2.5, 'output' => 10.0]]);

        $this->assertSame(2.5, $table->get('gpt-4o')?->getInput());
    }

    #[TestWith(['gpt-4o-mini'])]
    #[TestWith(['gpt-4o-mini-2024-07-18'])]
    #[TestWith(['gpt-4o-audio-preview'])]
    public function testOtherModelSharingThePrefixIsNotPriced(string $model)
    {
        $table = PriceTable::fromArray(['gpt-4o' => ['input' => 2.5, 'output' => 10.0]]);

        $this->assertNull($table->get($model));
    }

    #[TestWith(['gpt-4o-2024-08-06', 'gpt-4o'])]
    #[TestWith(['gpt-4o-mini-2024-07-18', 'gpt-4o-mini'])]
    #[TestWith(['claude-sonnet-4-5-20250929', 'claude-sonnet-4-5'])]
    public function testDatedSnapshotIsPricedAsItsModel(string $snapshot, string $model)
    {
        $table = PriceTable::fromArray([
            'gpt-4o' => ['input' => 2.5, 'output' => 10.0],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.6],
            'claude-sonnet-4-5' => ['input' => 3.0, 'output' => 15.0],
        ]);

        $this->assertSame($table->get($model), $table->get($snapshot));
    }

    public function testExactlyPricedSnapshotWinsOverItsModel()
    {
        $table = PriceTable::fromArray([
            'gpt-4o' => ['input' => 2.5, 'output' => 10.0],
            'gpt-4o-2024-05-13' => ['input' => 5.0, 'output' => 15.0],
        ]);

        $this->assertSame(5.0, $table->get('gpt-4o-2024-05-13')?->getInput());
    }

    public function testExactlyPricedFallbackWinsOverAnUndatedModel()
    {
        $table = PriceTable::fromArray([
            'my-model' => ['input' => 1.0, 'output' => 1.0],
            'my-alias' => ['input' => 2.0, 'output' => 2.0],
        ]);

        $this->assertSame(2.0, $table->get('my-model-20250101', 'my-alias')?->getInput());
    }

    public function testUndatedFallbackIsPriced()
    {
        $table = PriceTable::fromArray(['gpt-4o' => ['input' => 2.5, 'output' => 10.0]]);

        $this->assertSame(2.5, $table->get('unknown-model', 'gpt-4o-2024-08-06')?->getInput());
    }
}
