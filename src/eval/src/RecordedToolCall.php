<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval;

use Symfony\AI\Agent\Execution\Turn;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RecordedToolCall
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        private readonly string $name,
        private readonly array $arguments,
        private readonly mixed $result = null,
        private readonly bool $failed = false,
    ) {
    }

    /**
     * @param list<Turn> $turns
     *
     * @return list<self>
     */
    public static function fromTurns(array $turns): array
    {
        $toolCalls = [];
        foreach ($turns as $turn) {
            foreach ($turn->getToolResults() as $toolResult) {
                $toolCall = $toolResult->getToolCall();
                $toolCalls[] = new self($toolCall->getName(), $toolCall->getArguments(), $toolResult->getResult(), $toolResult->isFailure());
            }
        }

        return $toolCalls;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function isFailed(): bool
    {
        return $this->failed;
    }
}
