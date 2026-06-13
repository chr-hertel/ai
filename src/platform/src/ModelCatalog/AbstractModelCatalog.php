<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\ModelCatalog;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelRequirements;
use Symfony\AI\Platform\Task;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
abstract class AbstractModelCatalog implements ModelCatalogInterface
{
    /**
     * @var array<string, array{class: class-string, tasks?: list<Task>, input?: list<Modality>, output?: list<Modality>, features?: list<Feature>}>
     */
    protected array $models;

    public function getModel(string $modelName): Model
    {
        if ('' === $modelName) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }

        $parsed = $this->parseModelName($modelName);
        $actualModelName = $parsed['name'];
        $catalogKey = $parsed['catalogKey'];
        $options = $parsed['options'];

        if (!isset($this->models[$catalogKey])) {
            throw new ModelNotFoundException(\sprintf('Model "%s" not found in "%s".', $actualModelName, static::class));
        }

        $modelConfig = $this->models[$catalogKey];
        $modelClass = $modelConfig['class'];

        if (!class_exists($modelClass)) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" does not exist.', $modelClass));
        }

        $model = self::buildModel($modelClass, $actualModelName, $modelConfig, $options);
        if (!$model instanceof Model) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" must extend "%s".', $modelClass, Model::class));
        }

        return $model;
    }

    /**
     * @return array<string, array{class: class-string, tasks?: list<Task>, input?: list<Modality>, output?: list<Modality>, features?: list<Feature>}>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    public function findMatching(ModelRequirements $requirements): array
    {
        $matches = [];
        foreach ($this->models as $name => $modelConfig) {
            if (!class_exists($modelConfig['class'])) {
                continue;
            }

            $model = self::buildModel($modelConfig['class'], $name, $modelConfig);
            if ($model instanceof Model && $model->satisfies($requirements)) {
                $matches[$name] = $model;
            }
        }

        return $matches;
    }

    /**
     * @param class-string                                                                                                              $modelClass
     * @param array{class: class-string, tasks?: list<Task>, input?: list<Modality>, output?: list<Modality>, features?: list<Feature>} $modelConfig
     * @param array<string, mixed>                                                                                                      $options
     */
    private static function buildModel(string $modelClass, string $name, array $modelConfig, array $options = []): object
    {
        return new $modelClass(
            $name,
            $modelConfig['tasks'] ?? [],
            $modelConfig['input'] ?? [],
            $modelConfig['output'] ?? [],
            $modelConfig['features'] ?? [],
            $options,
        );
    }

    /**
     * Extracts model name and options from a model name string that may contain query parameters.
     * Also resolves size variants (e.g., "model:23b") to their base model for catalog lookup.
     *
     * @param string $modelName The model name, potentially with query parameters (e.g., "model-name?param=value&other=123")
     *
     * @return array{name: string, catalogKey: string, options: array<string, mixed>} An array containing the model name, catalog lookup key, and parsed options
     */
    protected function parseModelName(string $modelName): array
    {
        $options = [];
        $actualModelName = $modelName;

        if (str_contains($modelName, '?')) {
            [$actualModelName, $queryString] = explode('?', $modelName, 2);

            if ('' === $actualModelName) {
                throw new InvalidArgumentException('Model name cannot be empty.');
            }

            parse_str($queryString, $options);

            $options = self::convertScalarStrings($options);
        }

        // Determine catalog key: try exact match first, then fall back to base model
        $catalogKey = $actualModelName;
        if (!isset($this->models[$actualModelName]) && str_contains($actualModelName, ':')) {
            $baseModelName = explode(':', $actualModelName, 2)[0];
            if (isset($this->models[$baseModelName])) {
                $catalogKey = $baseModelName;
            }
        }

        return [
            'name' => $actualModelName,
            'catalogKey' => $catalogKey,
            'options' => $options,
        ];
    }

    /**
     * Recursively converts numeric strings to integers or floats in an array.
     *
     * @param array<string, mixed> $data The array to process
     *
     * @return array<string, mixed> The array with numeric and boolean-like strings converted to appropriate numeric/boolean types
     */
    private static function convertScalarStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = self::convertScalarStrings($value);
            } elseif ('true' === $value) {
                $data[$key] = true;
            } elseif ('false' === $value) {
                $data[$key] = false;
            } elseif (is_numeric($value) && \is_string($value)) {
                // Convert to int if it's a whole number, otherwise to float
                $data[$key] = str_contains($value, '.') || str_contains($value, 'e') || str_contains($value, 'E') ? (float) $value : (int) $value;
            }
        }

        return $data;
    }
}
