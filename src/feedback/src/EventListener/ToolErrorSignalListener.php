<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\EventListener;

use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\FeedbackRecorderInterface;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Feedback\Source;

/**
 * Records a failed tool call as an implicit, negative signal on its run.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolErrorSignalListener
{
    public function __construct(
        private readonly FeedbackRecorderInterface $recorder,
    ) {
    }

    public function __invoke(ToolCallsExecuted $event): void
    {
        $runContext = $event->getRequest()?->getContext()->get(RunContext::class);
        if (null === $runContext) {
            return;
        }

        foreach ($event->getToolResults() as $toolResult) {
            if (!$toolResult->isFailure()) {
                continue;
            }

            $failure = $toolResult->getFailure();

            $this->recorder->record(new Feedback(
                $runContext->getRunId(),
                Signal::ToolError,
                false,
                Source::Application,
                comment: $failure?->getMessage(),
                metadata: [
                    'tool' => $toolResult->getToolCall()->getName(),
                    'exception' => null !== $failure ? $failure::class : null,
                ],
            ));
        }
    }
}
