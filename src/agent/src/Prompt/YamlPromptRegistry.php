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

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads versioned prompts from YAML files, one file per prompt:
 *
 *     name: support/system
 *     versions:
 *         '2026-08-v2':
 *             template: |
 *                 You are the support assistant of {shop_name}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class YamlPromptRegistry implements PromptRegistryInterface
{
    private ?InMemoryPromptRegistry $registry = null;

    /**
     * @param list<string> $paths directories containing *.yaml or *.yml prompt files, the last version of a prompt is its latest
     */
    public function __construct(
        private readonly array $paths,
    ) {
        if (!class_exists(Yaml::class)) {
            throw new RuntimeException('For using the YamlPromptRegistry, the symfony/yaml package is required. Try running "composer require symfony/yaml".');
        }
    }

    public function get(string $name, ?string $version = null): Prompt
    {
        return $this->load()->get($name, $version);
    }

    public function versions(string $name): array
    {
        return $this->load()->versions($name);
    }

    private function load(): InMemoryPromptRegistry
    {
        if (null !== $this->registry) {
            return $this->registry;
        }

        $prompts = [];
        foreach ($this->paths as $path) {
            $directory = rtrim($path, '/');
            // GLOB_BRACE is not available on every platform
            $files = array_merge(glob($directory.'/*.yaml') ?: [], glob($directory.'/*.yml') ?: []);
            sort($files);

            foreach ($files as $file) {
                $data = Yaml::parseFile($file);

                if (!\is_array($data) || !isset($data['name'], $data['versions']) || !\is_array($data['versions'])) {
                    throw new InvalidArgumentException(\sprintf('The prompt file "%s" must define "name" and "versions".', $file));
                }

                foreach ($data['versions'] as $version => $definition) {
                    $template = \is_array($definition) ? ($definition['template'] ?? null) : $definition;
                    if (!\is_string($template)) {
                        throw new InvalidArgumentException(\sprintf('The version "%s" of the prompt "%s" in "%s" must define a "template" string.', $version, $data['name'], $file));
                    }

                    if (isset($prompts[$data['name']][(string) $version])) {
                        throw new InvalidArgumentException(\sprintf('The version "%s" of the prompt "%s" is defined more than once, again in "%s".', $version, $data['name'], $file));
                    }

                    $prompts[$data['name']][(string) $version] = $template;
                }
            }
        }

        return $this->registry = new InMemoryPromptRegistry($prompts);
    }
}
