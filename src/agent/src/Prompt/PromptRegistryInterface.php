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

use Symfony\AI\Agent\Exception\InvalidArgumentException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface PromptRegistryInterface
{
    /**
     * @param string|null $version null for the latest version
     *
     * @throws InvalidArgumentException if the prompt or the version does not exist
     */
    public function get(string $name, ?string $version = null): Prompt;

    /**
     * @return list<string> the versions of the prompt, oldest first
     */
    public function versions(string $name): array;
}
