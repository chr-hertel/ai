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

/**
 * Reason why an agent execution pauses for human interaction.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum InteractionReason: string
{
    case Confirmation = 'confirmation';
    case Input = 'input';
    case Choice = 'choice';
    case ToolApproval = 'tool_approval';
}
