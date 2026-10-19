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
use Symfony\AI\Agent\Context\AgentResult;
use Symfony\AI\Agent\Context\ResultAwareContextProcessorInterface;

final class RecordingProcessor implements ResultAwareContextProcessorInterface
{
    public bool $processed = false;

    public ?AgentResult $result = null;

    public static function supportedTypes(): array
    {
        return [];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
        $this->processed = true;
    }

    public function processResult(AgentResult $result, AgentContext $context): void
    {
        $this->result = $result;
    }
}
