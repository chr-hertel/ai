<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Run;

/**
 * Receives the run context of the agent run before each step of it.
 *
 * The agent passes it to its input and output processors, its toolbox and its tool executor. Toolboxes pass it on to
 * their tools and to the tool call events.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface RunContextAwareInterface
{
    public function setRunContext(RunContext $runContext): void;
}
