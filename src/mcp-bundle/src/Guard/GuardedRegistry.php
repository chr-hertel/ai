<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Guard;

use Mcp\Capability\Registry;
use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\Registry\ResourceReference;
use Mcp\Capability\Registry\ResourceTemplateReference;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Exception\InvalidCursorException;
use Mcp\Exception\PromptNotFoundException;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Exception\ToolNotFoundException;
use Mcp\Schema\Page;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;

/**
 * Hides the elements a guard denies from the server: from every list, and from every lookup by name or URI.
 *
 * Only the server builder writes through this decorator; everything else keeps using the inner registry.
 *
 * @phpstan-import-type Handler from ElementReference
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class GuardedRegistry implements RegistryInterface
{
    /**
     * @param iterable<McpGuardInterface> $guards
     */
    public function __construct(
        private readonly RegistryInterface $inner,
        private readonly iterable $guards,
    ) {
    }

    public function registerTool(Tool $tool, callable|array|string $handler): ToolReference
    {
        return $this->write(static fn (RegistryInterface $registry): ToolReference => $registry->registerTool($tool, $handler));
    }

    public function registerResource(ResourceDefinition $resource, callable|array|string $handler): ResourceReference
    {
        return $this->write(static fn (RegistryInterface $registry): ResourceReference => $registry->registerResource($resource, $handler));
    }

    public function registerResourceTemplate(ResourceTemplate $template, callable|array|string $handler, array $completionProviders = []): ResourceTemplateReference
    {
        return $this->write(static fn (RegistryInterface $registry): ResourceTemplateReference => $registry->registerResourceTemplate($template, $handler, $completionProviders));
    }

    public function registerPrompt(Prompt $prompt, callable|array|string $handler, array $completionProviders = []): PromptReference
    {
        return $this->write(static fn (RegistryInterface $registry): PromptReference => $registry->registerPrompt($prompt, $handler, $completionProviders));
    }

    public function unregisterTool(string $name): void
    {
        $this->write(static fn (RegistryInterface $registry) => $registry->unregisterTool($name));
    }

    public function unregisterResource(string $uri): void
    {
        $this->write(static fn (RegistryInterface $registry) => $registry->unregisterResource($uri));
    }

    public function unregisterResourceTemplate(string $uriTemplate): void
    {
        $this->write(static fn (RegistryInterface $registry) => $registry->unregisterResourceTemplate($uriTemplate));
    }

    public function unregisterPrompt(string $name): void
    {
        $this->write(static fn (RegistryInterface $registry) => $registry->unregisterPrompt($name));
    }

    public function publishesListChanges(): bool
    {
        return $this->inner->publishesListChanges();
    }

    public function hasTool(string $name): bool
    {
        return $this->inner->hasTool($name);
    }

    public function hasResource(string $uri): bool
    {
        return $this->inner->hasResource($uri);
    }

    public function hasResourceTemplate(string $uriTemplate): bool
    {
        return $this->inner->hasResourceTemplate($uriTemplate);
    }

    public function hasPrompt(string $name): bool
    {
        return $this->inner->hasPrompt($name);
    }

    public function hasTools(): bool
    {
        return $this->inner->hasTools();
    }

    public function getTools(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->paginate($this->inner->getTools()->references, $limit, $cursor);
    }

    public function getTool(string $name): ToolReference
    {
        $reference = $this->inner->getTool($name);

        if (!$this->isGranted($reference->tool)) {
            throw new ToolNotFoundException($name);
        }

        return $reference;
    }

    public function hasResources(): bool
    {
        return $this->inner->hasResources();
    }

    public function getResources(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->paginate($this->inner->getResources()->references, $limit, $cursor);
    }

    public function getResource(string $uri, bool $includeTemplates = true): ResourceReference|ResourceTemplateReference
    {
        $reference = $this->inner->getResource($uri, $includeTemplates);
        $element = $reference instanceof ResourceReference ? $reference->resource : $reference->resourceTemplate;

        if (!$this->isGranted($element)) {
            throw new ResourceNotFoundException($uri);
        }

        return $reference;
    }

    public function hasResourceTemplates(): bool
    {
        return $this->inner->hasResourceTemplates();
    }

    public function getResourceTemplates(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->paginate($this->inner->getResourceTemplates()->references, $limit, $cursor);
    }

    public function getResourceTemplate(string $uriTemplate): ResourceTemplateReference
    {
        $reference = $this->inner->getResourceTemplate($uriTemplate);

        if (!$this->isGranted($reference->resourceTemplate)) {
            throw new ResourceNotFoundException($uriTemplate);
        }

        return $reference;
    }

    public function hasPrompts(): bool
    {
        return $this->inner->hasPrompts();
    }

    public function getPrompts(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->paginate($this->inner->getPrompts()->references, $limit, $cursor);
    }

    public function getPrompt(string $name): PromptReference
    {
        $reference = $this->inner->getPrompt($name);

        if (!$this->isGranted($reference->prompt)) {
            throw new PromptNotFoundException($name);
        }

        return $reference;
    }

    private function isGranted(Tool|Prompt|ResourceDefinition|ResourceTemplate $element): bool
    {
        foreach ($this->guards as $guard) {
            if (!$guard->isGranted($element)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filters before paginating, so pages stay full, with the offset cursor of the SDK's registry.
     *
     * @template T of Tool|Prompt|ResourceDefinition|ResourceTemplate
     *
     * @param array<int|string, T> $elements
     *
     * @return Page<T>
     */
    private function paginate(array $elements, ?int $limit, ?string $cursor): Page
    {
        $elements = array_filter($elements, $this->isGranted(...));

        if (null === $limit) {
            return new Page($elements, null);
        }

        $offset = 0;
        if (null !== $cursor) {
            $decoded = base64_decode($cursor, true);
            if (false === $decoded || !is_numeric($decoded)) {
                throw new InvalidCursorException($cursor);
            }

            $offset = (int) $decoded;
            if ($offset < 0 || $offset > \count($elements)) {
                throw new InvalidCursorException($cursor);
            }
        }

        $nextOffset = $offset + $limit;

        return new Page(
            array_values(\array_slice($elements, $offset, $limit)),
            $nextOffset < \count($elements) ? base64_encode((string) $nextOffset) : null,
        );
    }

    /**
     * @template T
     *
     * @param \Closure(RegistryInterface): T $write
     *
     * @return T
     */
    private function write(\Closure $write): mixed
    {
        if (!$this->inner instanceof Registry) {
            return $write($this->inner);
        }

        // Runs through loadFrom() so the builder's load announces no list change, as it would without the decorator.
        $loader = new class($write) implements LoaderInterface {
            public bool $done = false;
            public mixed $result = null;

            /**
             * @param \Closure(RegistryInterface): mixed $write
             */
            public function __construct(private readonly \Closure $write)
            {
            }

            public function load(RegistryInterface $registry): void
            {
                $this->result = ($this->write)($registry);
                $this->done = true;
            }
        };

        $this->inner->loadFrom($loader);

        if (!$loader->done) {
            return $write($this->inner);
        }

        return $loader->result;
    }
}
