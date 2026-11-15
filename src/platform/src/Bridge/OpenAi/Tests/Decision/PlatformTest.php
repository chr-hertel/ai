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
use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\OpenAi\Decision\DecisionModelCatalog;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class PlatformTest extends TestCase
{
    public function testItEvaluatesThroughTheDecisionsApiWithTheDecisionModelCatalog()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://api.openai.com/v1/decisions', $url);
            $this->assertSame('{"model":"gpt-6-luna","input":"I need this fixed by tomorrow.","questions":[{"name":"is_urgent","type":"predicate","instructions":"Does this convey urgency?"}]}', $options['body']);

            return new JsonMockResponse(['model' => 'gpt-6-luna', 'answers' => [['type' => 'predicate', 'name' => 'is_urgent', 'probability' => 0.97]]]);
        });

        $platform = Factory::createPlatform('sk-api-key', $httpClient, modelCatalog: new DecisionModelCatalog());
        $result = $platform->invoke('gpt-6-luna', new Evaluation('I need this fixed by tomorrow.', [
            'is_urgent' => new NoulQuestion('Does this convey urgency?'),
        ]));

        $this->assertTrue($result->asObject(Answers::class)->getNoul('is_urgent')->isTrue());
    }
}
