<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Security;

use Mcp\Capability\Registry\ResourceReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Exception\NotFoundExceptionInterface;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Checks the #[IsGranted] attributes of the elements an HTTP request calls before the MCP server runs.
 *
 * A denial then reaches the firewall instead of becoming a JSON-RPC error, so that clients get a 401 or a
 * 403 challenge, e.g. "insufficient_scope" for a missing OAuth2 scope.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class IsGrantedListener
{
    public function __construct(
        private readonly string $controller,
        private readonly RegistryInterface $registry,
        private readonly IsGrantedGuard $guard,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if ($this->controller !== $request->attributes->get('_controller') || !$request->isMethod('POST')) {
            return;
        }

        $messages = json_decode($request->getContent(), true);
        if (!\is_array($messages)) {
            return;
        }

        foreach (array_is_list($messages) ? $messages : [$messages] as $message) {
            if (\is_array($message) && null !== $element = $this->calledElement($message)) {
                $this->guard->denyAccessUnlessGranted($element);
            }
        }
    }

    /**
     * @param array<mixed> $message
     */
    private function calledElement(array $message): Tool|Prompt|ResourceDefinition|ResourceTemplate|null
    {
        $params = \is_array($message['params'] ?? null) ? $message['params'] : [];

        try {
            return match ($message['method'] ?? null) {
                'tools/call' => \is_string($params['name'] ?? null) ? $this->registry->getTool($params['name'])->tool : null,
                'prompts/get' => \is_string($params['name'] ?? null) ? $this->registry->getPrompt($params['name'])->prompt : null,
                'resources/read' => \is_string($params['uri'] ?? null) ? $this->resource($params['uri']) : null,
                default => null,
            };
        } catch (NotFoundExceptionInterface) {
            // the server answers an unknown element itself
            return null;
        }
    }

    private function resource(string $uri): ResourceDefinition|ResourceTemplate
    {
        $reference = $this->registry->getResource($uri);

        return $reference instanceof ResourceReference ? $reference->resource : $reference->resourceTemplate;
    }
}
