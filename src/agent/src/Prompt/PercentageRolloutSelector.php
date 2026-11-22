<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Prompt;

use Symfony\AI\Agent\Run\RunContext;

/**
 * Serves a candidate version of a prompt to a stable share of the traffic.
 *
 * The bucket is derived from the "conversation" attribute of the run context if set, so a conversation never
 * switches prompts mid-way, then from the user ID and from the run ID otherwise.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PercentageRolloutSelector implements PromptVariantSelectorInterface
{
    /**
     * @param int<0, 100> $percentage share of the traffic getting the candidate version
     */
    public function __construct(
        private readonly string $promptName,
        private readonly string $candidate,
        private readonly string $baseline,
        private readonly int $percentage,
    ) {
    }

    public function select(string $promptName, RunContext $context): ?string
    {
        if ($promptName !== $this->promptName) {
            return null;
        }

        $key = $context->getAttributes()['conversation'] ?? $context->getUserId() ?? $context->getRunId();

        // salted, so the same conversations do not get the candidate of every rollout
        $bucket = hexdec(substr(hash('xxh128', $this->promptName."\0".$this->candidate."\0".$key), 0, 7)) % 100;

        return $bucket < $this->percentage ? $this->candidate : $this->baseline;
    }
}
