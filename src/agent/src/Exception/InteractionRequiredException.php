<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Exception;

use Symfony\AI\Agent\Execution\Update\Interaction;

/**
 * Thrown when an agent execution pauses for human interaction but the caller
 * did not register an interaction handler (e.g. when using Agent::call()).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InteractionRequiredException extends RuntimeException
{
    public function __construct(
        private readonly Interaction $interaction,
    ) {
        parent::__construct(\sprintf('The agent execution requires human interaction ("%s"). Use Agent::run() with an interaction handler instead of Agent::call().', $interaction->getPrompt()));
    }

    public function getInteraction(): Interaction
    {
        return $this->interaction;
    }
}
