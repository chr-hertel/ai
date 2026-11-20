<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Eval;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Prompt\InMemoryPromptRegistry;
use Symfony\AI\Agent\Prompt\PromptInputProcessor;
use Symfony\AI\AiBundle\Eval\SuiteAgentFactory;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\Variant;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class SuiteAgentFactoryTest extends TestCase
{
    public function testVariantSelectsPlatformModelAndPromptVersion()
    {
        $prompts = [];
        $platform = static function (string $name) use (&$prompts) {
            return MockPlatformFactory::createPlatform(static function (Model $model, array $payload) use (&$prompts, $name): string {
                $prompts[] = [$name, $model->getName(), str_contains((string) json_encode($payload), 'New Acme.') ? 'v2' : 'v1'];

                return 'Hi';
            });
        };
        $registry = new InMemoryPromptRegistry(['support/system' => ['v1' => 'Old {shop}.', 'v2' => 'New {shop}.']]);
        $factory = new SuiteAgentFactory(
            new ServiceLocator(['default' => static fn () => $platform('default'), 'other' => static fn () => $platform('other')]),
            'default',
            'gpt-4o',
            'support',
            [new PromptInputProcessor($registry, 'support/system', 'v1', ['shop' => 'Acme'])],
            promptRegistry: $registry,
            promptName: 'support/system',
            promptVariables: ['shop' => 'Acme'],
        );
        $case = new EvalCase('case-1', 'Hello');

        $factory(new Variant('baseline'), null, $case)->call('Hello')->getContent();
        $factory(new Variant('candidate', ['platform' => 'other', 'model' => 'gpt-4o-mini', 'prompt_version' => 'v2']), null, $case)->call('Hello')->getContent();

        $this->assertSame([['default', 'gpt-4o', 'v1'], ['other', 'gpt-4o-mini', 'v2']], $prompts);
    }

    public function testPromptVersionNeedsARegistryPrompt()
    {
        $factory = new SuiteAgentFactory(new ServiceLocator(['default' => static fn () => MockPlatformFactory::createPlatform('Hi')]), 'default', 'gpt-4o', 'support');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The variant "candidate" sets a "prompt_version", but the agent "support" has no prompt of the registry.');

        $factory(new Variant('candidate', ['prompt_version' => 'v2']), null, new EvalCase('case-1', 'Hello'));
    }
}
