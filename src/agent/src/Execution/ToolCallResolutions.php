<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Input of an agent call that resumes a suspended execution, resolving the tool calls it left unanswered.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallResolutions
{
    /**
     * @param MessageBag                        $messages    the suspended conversation, or an empty bag with its id to continue the stored one
     * @param array<string, ToolCallResolution> $resolutions keyed by tool call id
     */
    public function __construct(
        private readonly MessageBag $messages,
        private readonly array $resolutions,
    ) {
        if ([] === $resolutions) {
            throw new InvalidArgumentException('At least one tool call resolution is required.');
        }

        foreach ($resolutions as $resolution) {
            if (!$resolution instanceof ToolCallResolution) {
                throw new InvalidArgumentException(\sprintf('The resolutions must be instances of "%s" keyed by tool call id.', ToolCallResolution::class));
            }
        }
    }

    public function getMessages(): MessageBag
    {
        return $this->messages;
    }

    /**
     * @return array<string, ToolCallResolution> keyed by tool call id
     */
    public function getResolutions(): array
    {
        return $this->resolutions;
    }
}
