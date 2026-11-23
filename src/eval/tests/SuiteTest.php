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
use Symfony\AI\Agent\Agent;
use Symfony\AI\Eval\Dataset;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\Suite;
use Symfony\AI\Eval\Variant;
use Symfony\AI\Platform\Test\MockPlatformFactory;

final class SuiteTest extends TestCase
{
    public function testRejectsDuplicateCaseIdsAcrossDatasets()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The case ID "hello" is used in the datasets "golden" and "regressions" of the suite "greetings".');

        new Suite(
            'greetings',
            [new Dataset('golden', [new EvalCase('hello', 'Hi')]), new Dataset('regressions', [new EvalCase('hello', 'Hello')])],
            [new Variant('polite')],
            static fn (): Agent => new Agent(MockPlatformFactory::createPlatform('Hello!'), 'model'),
            [],
        );
    }
}
