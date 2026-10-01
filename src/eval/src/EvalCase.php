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

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * A single case of a dataset: the input for the agent, deterministic tool results and the expectations to check.
 *
 * @phpstan-type Expectations array<string, mixed>
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EvalCase
{
    /**
     * @param list<array{user?: string, assistant?: string}>|string $input        the conversation, the last message is the one to answer
     * @param list<string>                                          $labels       e.g. failure categories, used to group pass rates
     * @param array<string, mixed>                                  $fixtures     tool results keyed by tool name, see FixtureToolbox
     * @param array<string, mixed>                                  $expectations checked by the evaluators, e.g. "tools_called" or "judge.criteria"
     * @param string|null                                           $sourceRun    the run (trace) ID the case was taken from
     */
    public function __construct(
        private readonly string $id,
        private readonly array|string $input,
        private readonly array $labels = [],
        private readonly array $fixtures = [],
        private readonly array $expectations = [],
        private readonly ?string $sourceRun = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return list<array{user?: string, assistant?: string}>|string
     */
    public function getInput(): array|string
    {
        return $this->input;
    }

    public function getMessages(): MessageBag
    {
        if (\is_string($this->input)) {
            return new MessageBag(Message::ofUser($this->input));
        }

        $messages = new MessageBag();
        foreach ($this->input as $message) {
            if (isset($message['user'])) {
                $messages->add(Message::ofUser($message['user']));
            }

            if (isset($message['assistant'])) {
                $messages->add(Message::ofAssistant($message['assistant']));
            }
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function hasLabel(string $label): bool
    {
        return \in_array($label, $this->labels, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFixtures(): array
    {
        return $this->fixtures;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExpectations(): array
    {
        return $this->expectations;
    }

    public function hasExpectation(string $path): bool
    {
        return null !== $this->expectation($path);
    }

    /**
     * Reads an expectation by its dotted path, e.g. "judge.criteria".
     */
    public function expectation(string $path, mixed $default = null): mixed
    {
        $value = $this->expectations;
        foreach (explode('.', $path) as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return $default;
            }

            $value = $value[$key];
        }

        return $value;
    }

    public function getSourceRun(): ?string
    {
        return $this->sourceRun;
    }
}
