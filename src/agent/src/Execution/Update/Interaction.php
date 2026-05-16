<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution\Update;

use Symfony\AI\Agent\Execution\InteractionReason;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Agent\Execution\UpdateType;

/**
 * A blocking update: the execution pauses until a human sends a response back.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Interaction implements UpdateInterface
{
    /**
     * @param array<string, mixed> $schema optional schema or list of choices describing the expected response
     */
    public function __construct(
        private readonly InteractionReason $reason,
        private readonly string $prompt,
        private readonly array $schema = [],
    ) {
    }

    public function getType(): UpdateType
    {
        return UpdateType::Interaction;
    }

    public function getReason(): InteractionReason
    {
        return $this->reason;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }
}
