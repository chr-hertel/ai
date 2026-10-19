<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures;

use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Execution\Execution;

final class DelegatingProcessor implements ContextProcessorInterface
{
    public function __construct(
        private readonly Execution $execution,
        private readonly ?string $target = null,
    ) {
    }

    public static function supportedTypes(): array
    {
        return [];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
        $context->delegate($this->execution, $this->target);
    }
}
