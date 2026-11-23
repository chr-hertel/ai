<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Agent\Execution\Turn;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Output
{
    /**
     * @param non-empty-string|Model $model
     * @param array<string, mixed>   $options
     * @param list<Turn>             $turns   the model rounds of the run, each with the results of the tools it requested
     */
    public function __construct(
        private readonly string|Model $model,
        private ResultInterface $result,
        private readonly MessageBag $messageBag,
        private readonly array $options = [],
        private readonly RunContext $runContext = new RunContext(),
        private readonly array $turns = [],
    ) {
    }

    /**
     * @return non-empty-string|Model
     */
    public function getModel(): string|Model
    {
        return $this->model;
    }

    public function getResult(): ResultInterface
    {
        return $this->result;
    }

    public function setResult(ResultInterface $result): void
    {
        $this->result = $result;
    }

    public function getMessageBag(): MessageBag
    {
        return $this->messageBag;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getRunContext(): RunContext
    {
        return $this->runContext;
    }

    /**
     * @return list<Turn>
     */
    public function getTurns(): array
    {
        return $this->turns;
    }
}
