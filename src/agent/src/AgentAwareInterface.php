<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @deprecated since Symfony AI 1.0, context processors receive the agent via {@see \Symfony\AI\Agent\Context\AgentContext}.
 */
interface AgentAwareInterface
{
    public function setAgent(AgentInterface $agent): void;
}
