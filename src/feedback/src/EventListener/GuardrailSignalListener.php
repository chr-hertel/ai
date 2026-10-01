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

use Symfony\AI\Agent\Event\GuardrailTriggered;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\FeedbackRecorderInterface;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Feedback\Source;

/**
 * Records every guardrail intervention as an implicit signal on its run, e.g. denied refunds or stopped runaway loops.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class GuardrailSignalListener
{
    public function __construct(
        private readonly FeedbackRecorderInterface $recorder,
    ) {
    }

    public function __invoke(GuardrailTriggered $event): void
    {
        $runContext = $event->getRunContext();
        if (null === $runContext) {
            return;
        }

        $this->recorder->record(new Feedback(
            $runContext->getRunId(),
            Signal::Guardrail,
            $event->getAction(),
            Source::Application,
            name: 'guardrail.'.$event->getGuardrail(),
            comment: $event->getReason(),
            metadata: [...$event->getDetails(), 'guardrail' => $event->getGuardrail()],
        ));
    }
}
