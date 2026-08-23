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

/**
 * A toolbox both describes the available tools and invokes them.
 *
 * The two responsibilities live on separate interfaces — {@see ToolCatalogInterface} (advertise tools to
 * the model) and {@see ToolInvokerInterface} (run a tool call) — because they answer to different callers
 * at different moments. This composite keeps the convenient "one object holds the agent's tools" ergonomics
 * (e.g. `new Toolbox([$tool])`) while letting each consumer depend on just the role it needs.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ToolboxInterface extends ToolCatalogInterface, ToolInvokerInterface
{
}
