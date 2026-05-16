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

use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Exception\ExceptionInterface;
use Symfony\AI\Agent\Exception\InteractionRequiredException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface AgentInterface
{
    /**
     * Drives the agent to completion and returns the final result.
     *
     * @param array<string, mixed> $options
     *
     * @throws ExceptionInterface           When the agent encounters an error (e.g., unsupported model capabilities, invalid arguments, network failures, or processor errors)
     * @throws InteractionRequiredException When the execution pauses for human interaction; use {@see self::run()} instead
     */
    public function call(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): ResultInterface;

    /**
     * Starts the agent and returns a lazy, iterable {@see Execution} yielding
     * progress, interaction and result updates.
     *
     * @param array<string, mixed> $options
     */
    public function run(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): Execution;

    /**
     * Get the agent's name, which can be used for debugging or handoff configuration.
     */
    public function getName(): string;
}
