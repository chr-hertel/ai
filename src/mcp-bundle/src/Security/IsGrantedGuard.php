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

use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Symfony\AI\McpBundle\Guard\McpGuardInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Checks the #[IsGranted] attributes on the handler class and method of each MCP element.
 *
 * @phpstan-type ElementHandlers array{
 *     tool?: array<string, array{class-string, string}>,
 *     prompt?: array<string, array{class-string, string}>,
 *     resource?: array<string, array{class-string, string}>,
 *     resource_template?: array<string, array{class-string, string}>,
 * }
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class IsGrantedGuard implements McpGuardInterface
{
    /**
     * @var array<string, list<IsGranted>>
     */
    private array $attributes = [];

    /**
     * @param ElementHandlers $handlers the handler of each element that has #[IsGranted] attributes, by kind and by name or URI
     */
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly array $handlers,
    ) {
    }

    public function isGranted(Tool|Prompt|ResourceDefinition|ResourceTemplate $element): bool
    {
        foreach ($this->attributesOf($element) as $attribute) {
            if (!$this->authorizationChecker->isGranted($attribute->attribute)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws AccessDeniedException carrying the denied attribute, which lets the firewall answer with a challenge
     * @throws HttpException         when the attribute sets a status code
     */
    public function denyAccessUnlessGranted(Tool|Prompt|ResourceDefinition|ResourceTemplate $element): void
    {
        foreach ($this->attributesOf($element) as $attribute) {
            $accessDecision = new AccessDecision();

            if ($accessDecision->isGranted = $this->authorizationChecker->isGranted($attribute->attribute, null, $accessDecision)) {
                continue;
            }

            $message = $attribute->message ?: $accessDecision->getMessage();

            if (null !== $attribute->statusCode) {
                throw new HttpException($attribute->statusCode, $message, code: $attribute->exceptionCode ?? 0);
            }

            $exception = new AccessDeniedException($message, code: $attribute->exceptionCode ?? 403);
            $exception->setAttributes([$attribute->attribute]);
            $exception->setAccessDecision($accessDecision);

            throw $exception;
        }
    }

    /**
     * @param class-string $class
     *
     * @return list<IsGranted>
     */
    public static function readAttributes(string $class, string $method): array
    {
        $attributes = [
            ...(new \ReflectionClass($class))->getAttributes(IsGranted::class, \ReflectionAttribute::IS_INSTANCEOF),
            ...(new \ReflectionMethod($class, $method))->getAttributes(IsGranted::class, \ReflectionAttribute::IS_INSTANCEOF),
        ];

        return array_map(static fn (\ReflectionAttribute $attribute): IsGranted => $attribute->newInstance(), $attributes);
    }

    /**
     * @return list<IsGranted>
     */
    private function attributesOf(Tool|Prompt|ResourceDefinition|ResourceTemplate $element): array
    {
        [$kind, $key] = match (true) {
            $element instanceof Tool => ['tool', $element->name],
            $element instanceof Prompt => ['prompt', $element->name],
            $element instanceof ResourceDefinition => ['resource', $element->uri],
            $element instanceof ResourceTemplate => ['resource_template', $element->uriTemplate],
        };

        if (!isset($this->handlers[$kind][$key])) {
            return [];
        }

        return $this->attributes[$kind.' '.$key] ??= self::readAttributes(...$this->handlers[$kind][$key]);
    }
}
