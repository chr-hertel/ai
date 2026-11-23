<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum ToolCallState: string
{
    /**
     * The model requested the tool call.
     */
    case Requested = 'requested';

    /**
     * The tool call is held back until it gets approved or rejected, e.g. by a human.
     */
    case Suspended = 'suspended';

    /**
     * The suspended tool call got approved and is executed by the next agent call.
     */
    case Approved = 'approved';

    /**
     * The tool call got rejected and is answered with the reason instead of being executed.
     */
    case Rejected = 'rejected';
}
