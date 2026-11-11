<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Guard;

use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;

/**
 * Decides whether the current caller may see and use an MCP element.
 *
 * A denied element is left out of its list and answered as not found when a client requests it.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface McpGuardInterface
{
    public function isGranted(Tool|Prompt|ResourceDefinition|ResourceTemplate $element): bool;
}
