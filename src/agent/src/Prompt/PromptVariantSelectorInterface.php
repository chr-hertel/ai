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

use Symfony\AI\Agent\Context\RunContext;

/**
 * Picks the version of a prompt for a run, e.g. to roll out a new prompt version to a share of the traffic.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface PromptVariantSelectorInterface
{
    /**
     * @return string|null the version to use, null to use the configured version
     */
    public function select(string $promptName, RunContext $context): ?string;
}
