<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Event;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Dispatched after each platform invocation, before any tool handling.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelResponded
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly ResultInterface $result,
    ) {
    }

    public function getAgent(): AgentInterface
    {
        return $this->agent;
    }

    public function getResult(): ResultInterface
    {
        return $this->result;
    }
}
