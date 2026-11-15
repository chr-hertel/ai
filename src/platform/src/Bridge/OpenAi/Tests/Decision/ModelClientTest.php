<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Decision;
use Symfony\AI\Platform\Bridge\OpenAi\Decision\ModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testItSupportsOnlyDecisionModels()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->assertTrue($client->supports(new Decision('gpt-6-luna')));
        $this->assertFalse($client->supports(new Gpt('gpt-6-luna')));
    }

    public function testItSendsTheEvaluationToTheDecisionsEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://eu.api.openai.com/v1/decisions', $url);
            $this->assertContains('Authorization: Bearer sk-api-key', $options['headers']);
            $this->assertSame('{"model":"gpt-6-luna","input":"My invoice is wrong.","questions":[{"name":"is_bug","type":"predicate","instructions":"Is this a software defect?"}]}', $options['body']);

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'sk-api-key', Factory::REGION_EU))->request(new Decision('gpt-6-luna'), [
            'input' => 'My invoice is wrong.',
            'questions' => [['name' => 'is_bug', 'type' => 'predicate', 'instructions' => 'Is this a software defect?']],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRejectsAPayloadWithoutQuestions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "gpt-6-luna" requires an "Symfony\AI\Platform\Bridge\Decision\Evaluation" input, try running "composer require symfony/ai-decision-platform".');

        (new ModelClient(new MockHttpClient(), 'sk-api-key'))->request(new Decision('gpt-6-luna'), 'My invoice is wrong.');
    }
}
