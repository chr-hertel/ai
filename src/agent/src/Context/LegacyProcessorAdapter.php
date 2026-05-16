<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context;

use Symfony\AI\Agent\AgentAwareInterface;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;

/**
 * Adapts a deprecated InputProcessorInterface/OutputProcessorInterface so it
 * can run within the context processor chain.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class LegacyProcessorAdapter implements ResultAwareContextProcessorInterface
{
    public function __construct(
        private readonly InputProcessorInterface|OutputProcessorInterface $processor,
    ) {
    }

    public static function supportedTypes(): array
    {
        return [];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
        if (!$this->processor instanceof InputProcessorInterface) {
            return;
        }

        if ($this->processor instanceof AgentAwareInterface) {
            $this->processor->setAgent($context->getAgent());
        }

        $input = new Input($request->getModel(), $request->getMessageBag(), $request->getOptions());
        $this->processor->processInput($input);

        $request->setModel($input->getModel());
        $request->setMessageBag($input->getMessageBag());
        $request->setOptions($input->getOptions());
    }

    public function processResult(AgentResult $result, AgentContext $context): void
    {
        if (!$this->processor instanceof OutputProcessorInterface) {
            return;
        }

        if ($this->processor instanceof AgentAwareInterface) {
            $this->processor->setAgent($context->getAgent());
        }

        $output = new Output($result->getModel(), $result->getResult(), $result->getMessageBag(), $result->getOptions());
        $this->processor->processOutput($output);

        $result->setResult($output->getResult());
    }
}
