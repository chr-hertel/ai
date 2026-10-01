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

use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\Exception\RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * A named, versioned list of eval cases, usually stored as YAML file next to the code it tests.
 *
 * @implements \IteratorAggregate<int, EvalCase>
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Dataset implements \IteratorAggregate, \Countable
{
    /**
     * @param list<EvalCase> $cases
     */
    public function __construct(
        private readonly string $name,
        private readonly array $cases,
        private readonly ?string $description = null,
    ) {
    }

    public static function fromFile(string $path): self
    {
        if (!class_exists(Yaml::class)) {
            throw new RuntimeException('For loading datasets from YAML files, the symfony/yaml package is required. Try running "composer require symfony/yaml".');
        }

        if (!is_file($path)) {
            throw new InvalidArgumentException(\sprintf('The dataset file "%s" does not exist.', $path));
        }

        $data = Yaml::parseFile($path);
        if (!\is_array($data)) {
            throw new InvalidArgumentException(\sprintf('The dataset file "%s" is invalid.', $path));
        }

        return self::fromArray($data);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['name'], $data['cases']) || !\is_string($data['name']) || !\is_array($data['cases'])) {
            throw new InvalidArgumentException('A dataset requires a "name" and a list of "cases".');
        }

        $cases = [];
        foreach ($data['cases'] as $i => $case) {
            if (!\is_array($case) || !isset($case['input'])) {
                throw new InvalidArgumentException(\sprintf('Case #%d of dataset "%s" requires an "input".', $i, $data['name']));
            }

            $cases[] = new EvalCase(
                (string) ($case['id'] ?? $data['name'].'-'.$i),
                $case['input'],
                array_values($case['labels'] ?? []),
                $case['fixtures'] ?? [],
                $case['expect'] ?? [],
                $case['source_run'] ?? null,
            );
        }

        return new self($data['name'], $cases, $data['description'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $cases = [];
        foreach ($this->cases as $case) {
            $cases[] = array_filter([
                'id' => $case->getId(),
                'source_run' => $case->getSourceRun(),
                'labels' => $case->getLabels(),
                'input' => $case->getInput(),
                'fixtures' => $case->getFixtures(),
                'expect' => $case->getExpectations(),
            ], static fn (mixed $value): bool => null !== $value && [] !== $value);
        }

        return array_filter(['name' => $this->name, 'description' => $this->description, 'cases' => $cases], static fn (mixed $value): bool => null !== $value);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return list<EvalCase>
     */
    public function getCases(): array
    {
        return $this->cases;
    }

    public function with(EvalCase $case): self
    {
        return new self($this->name, [...$this->cases, $case], $this->description);
    }

    /**
     * @return \ArrayIterator<int, EvalCase>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->cases);
    }

    public function count(): int
    {
        return \count($this->cases);
    }
}
