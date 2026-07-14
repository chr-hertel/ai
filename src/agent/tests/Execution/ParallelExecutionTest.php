<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\ParallelExecution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;

final class ParallelExecutionTest extends TestCase
{
    public function testAwaitReturnsTheResultsKeyedByTheExecutionKey()
    {
        $platform = new InMemoryPlatform(static fn (mixed $model, mixed $input): TextResult => new TextResult('Answer to: '.$input->getMessages()[0]->asText()));
        $agent = new Agent($platform, 'gpt-4o');

        $results = $agent->callMany([
            'first' => 'What is 1+1?',
            'second' => 'What is 2+2?',
        ])->getResults();

        $this->assertSame(['first', 'second'], array_keys($results));
        $this->assertSame('Answer to: What is 1+1?', $results['first']->getContent());
        $this->assertSame('Answer to: What is 2+2?', $results['second']->getContent());
    }

    public function testItAcceptsAListOfInputs()
    {
        $platform = new InMemoryPlatform(static fn (mixed $model, mixed $input): TextResult => new TextResult('Answer to: '.$input->getMessages()[0]->asText()));
        $agent = new Agent($platform, 'gpt-4o');

        $results = $agent->callMany(['What is 1+1?', 'What is 2+2?'])->getResults();

        $this->assertSame([0, 1], array_keys($results));
    }

    public function testItAcceptsMessageBagsAsInput()
    {
        $platform = new InMemoryPlatform('Hi');
        $agent = new Agent($platform, 'gpt-4o');

        $results = $agent->callMany([new MessageBag(Message::ofUser('Hello'))])->getResults();

        $this->assertCount(1, $results);
        $this->assertSame('Hi', $results[0]->getContent());
    }

    public function testTheMergedUpdateStreamIsKeyedByTheExecutionKey()
    {
        $execution = new ParallelExecution([
            'first' => new Execution(static function (): \Generator {
                yield new Progress('model_request', 'Invoking model.');
                yield new ResultUpdate(new TextResult('One'));
            }),
            'second' => new Execution(static function (): \Generator {
                yield new ResultUpdate(new TextResult('Two'));
            }),
        ]);

        $keys = [];
        $results = [];
        foreach ($execution as $key => $update) {
            $keys[] = $key;

            if ($update instanceof ResultUpdate) {
                $results[$key] = $update->getResult()->getContent();
            }
        }

        // round-robin: first yields its progress, second its result, then first its result
        $this->assertSame(['first', 'second', 'first'], $keys);
        $this->assertSame(['second' => 'Two', 'first' => 'One'], $results);
    }
}
