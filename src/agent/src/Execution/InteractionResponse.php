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
 * The value a consumer sends back to resolve an {@see Update\Interaction}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InteractionResponse
{
    public function __construct(
        private readonly mixed $value = null,
    ) {
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function isApproved(): bool
    {
        return true === $this->value || 'yes' === $this->value || 'approve' === $this->value;
    }

    public static function approve(): self
    {
        return new self(true);
    }

    public static function deny(): self
    {
        return new self(false);
    }
}
