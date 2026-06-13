<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class Model
{
    /**
     * @var list<Task>
     */
    private readonly array $tasks;

    /**
     * @var list<Modality>
     */
    private readonly array $inputModalities;

    /**
     * @var list<Modality>
     */
    private readonly array $outputModalities;

    /**
     * @var list<Feature>
     */
    private readonly array $features;

    /**
     * @param non-empty-string     $name
     * @param Task[]               $tasks
     * @param Modality[]           $inputModalities
     * @param Modality[]           $outputModalities
     * @param Feature[]            $features
     * @param array<string, mixed> $options          The default options for the model usage
     */
    public function __construct(
        private readonly string $name,
        array $tasks = [],
        array $inputModalities = [],
        array $outputModalities = [],
        array $features = [],
        private readonly array $options = [],
    ) {
        if ('' === trim($name)) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }

        $this->tasks = array_values($tasks);
        $this->inputModalities = array_values($inputModalities);
        $this->outputModalities = array_values($outputModalities);
        $this->features = array_values($features);
    }

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function handles(Task $task): bool
    {
        return $task->equalsOneOf($this->tasks);
    }

    public function accepts(Modality $modality): bool
    {
        return $modality->equalsOneOf($this->inputModalities);
    }

    public function produces(Modality $modality): bool
    {
        return $modality->equalsOneOf($this->outputModalities);
    }

    public function has(Feature $feature): bool
    {
        return $feature->equalsOneOf($this->features);
    }

    public function isMultimodalInput(): bool
    {
        return \count($this->inputModalities) > 1;
    }

    public function satisfies(ModelRequirements $requirements): bool
    {
        foreach ($requirements->getTasks() as $task) {
            if (!$this->handles($task)) {
                return false;
            }
        }

        foreach ($requirements->getInputModalities() as $modality) {
            if (!$this->accepts($modality)) {
                return false;
            }
        }

        foreach ($requirements->getOutputModalities() as $modality) {
            if (!$this->produces($modality)) {
                return false;
            }
        }

        foreach ($requirements->getFeatures() as $feature) {
            if (!$this->has($feature)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<Task>
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * @return list<Modality>
     */
    public function getInputModalities(): array
    {
        return $this->inputModalities;
    }

    /**
     * @return list<Modality>
     */
    public function getOutputModalities(): array
    {
        return $this->outputModalities;
    }

    /**
     * @return list<Feature>
     */
    public function getFeatures(): array
    {
        return $this->features;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
