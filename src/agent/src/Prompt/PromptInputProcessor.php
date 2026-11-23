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

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Platform\Message\Message;

/**
 * Uses a prompt of the registry as system prompt and records its version in the run context.
 *
 * The prompt is resolved for every call, so a selector can pick another version per run.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PromptInputProcessor implements InputProcessorInterface
{
    /**
     * @param string|null                       $version   the configured version, null for the latest
     * @param array<string, string|\Stringable> $variables
     */
    public function __construct(
        private readonly PromptRegistryInterface $registry,
        private readonly string $promptName,
        private readonly ?string $version = null,
        private readonly array $variables = [],
        private readonly ?PromptVariantSelectorInterface $selector = null,
    ) {
    }

    public function processInput(Input $input): void
    {
        $runContext = $input->getRunContext();
        $version = $this->selector?->select($this->promptName, $runContext);
        $prompt = $this->registry->get($this->promptName, $version ?? $this->version);

        $input->setMessageBag($input->getMessageBag()->withSystemMessage(Message::forSystem($prompt->render($this->variables))));
        $input->setRunContext($runContext->withPrompt($prompt->getReference()));
    }
}
