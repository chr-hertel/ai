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

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolCatalogInterface;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\Gate\GateInterface;

/**
 * Datasets, the agent configurations to compare (the matrix of variants) and the gates a release has to pass.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Suite
{
    /**
     * @param list<Dataset>                                                         $datasets
     * @param list<Variant>                                                         $variants
     * @param \Closure(Variant, ToolboxInterface|null, EvalCase): AgentInterface   $agentFactory builds the agent under test, with the fixture toolbox of the case if the suite has a tool catalog
     * @param list<EvaluatorInterface>                                              $evaluators
     * @param list<GateInterface>                                                   $gates
     * @param ToolCatalogInterface|null                                             $toolCatalog  the real tools, answered by the fixtures of each case
     */
    public function __construct(
        private readonly string $name,
        private readonly array $datasets,
        private readonly array $variants,
        private readonly \Closure $agentFactory,
        private readonly array $evaluators,
        private readonly array $gates = [],
        private readonly int $repetitions = 1,
        private readonly ?ToolCatalogInterface $toolCatalog = null,
    ) {
        if ([] === $variants) {
            throw new InvalidArgumentException(\sprintf('The suite "%s" needs at least one variant.', $name));
        }

        if ($repetitions < 1) {
            throw new InvalidArgumentException('The number of repetitions must be at least 1.');
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<Dataset>
     */
    public function getDatasets(): array
    {
        return $this->datasets;
    }

    /**
     * @return list<EvalCase>
     */
    public function getCases(): array
    {
        $cases = [];
        foreach ($this->datasets as $dataset) {
            $cases = [...$cases, ...$dataset->getCases()];
        }

        return $cases;
    }

    /**
     * @return list<Variant>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    public function createAgent(Variant $variant, ?ToolboxInterface $toolbox, EvalCase $case): AgentInterface
    {
        return ($this->agentFactory)($variant, $toolbox, $case);
    }

    /**
     * @return list<EvaluatorInterface>
     */
    public function getEvaluators(): array
    {
        return $this->evaluators;
    }

    /**
     * @return list<GateInterface>
     */
    public function getGates(): array
    {
        return $this->gates;
    }

    public function getRepetitions(): int
    {
        return $this->repetitions;
    }

    public function getToolCatalog(): ?ToolCatalogInterface
    {
        return $this->toolCatalog;
    }
}
