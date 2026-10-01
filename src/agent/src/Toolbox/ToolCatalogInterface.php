<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Platform\Tool\Tool;

/**
 * Describes the tools available to an agent so they can be advertised to the model.
 *
 * This is the read-only half of the toolbox: it is consumed when a request is being built (to expose the
 * tool definitions to the model), never when a tool call comes back. Keeping it separate from
 * {@see ToolInvokerInterface} lets consumers that only need to know *which* tools exist depend on just that.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ToolCatalogInterface
{
    /**
     * @return Tool[]
     */
    public function getTools(): array;
}
