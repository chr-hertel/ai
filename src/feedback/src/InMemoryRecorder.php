<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback;

/**
 * Keeps feedback in memory, for tests and for reporting within the same process.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InMemoryRecorder implements FeedbackRecorderInterface
{
    /**
     * @var list<Feedback>
     */
    private array $feedback = [];

    public function record(Feedback $feedback): void
    {
        $this->feedback[] = $feedback;
    }

    /**
     * @return list<Feedback>
     */
    public function all(?string $runId = null, ?Signal $signal = null): array
    {
        return array_values(array_filter(
            $this->feedback,
            static fn (Feedback $feedback): bool => (null === $runId || $feedback->getRunId() === $runId)
                && (null === $signal || $feedback->getSignal() === $signal),
        ));
    }

    public function reset(): void
    {
        $this->feedback = [];
    }
}
