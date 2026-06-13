<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle;

use Http\Discovery\Psr17Factory;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Client as McpClient;
use Mcp\Client\Configuration as McpClientConfiguration;
use Mcp\Client\Protocol as McpClientProtocol;
use Mcp\Client\Transport\HttpTransport as McpHttpTransport;
use Mcp\Client\Transport\StdioTransport as McpStdioTransport;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Implementation;
use Mcp\Server\Handler\Notification\NotificationHandlerInterface;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Psr16SessionStore;
use Symfony\AI\McpBundle\Command\McpClientDebugCommand;
use Symfony\AI\McpBundle\Command\McpCommand;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
use Symfony\AI\McpBundle\Profiler\DataCollector;
use Symfony\AI\McpBundle\Profiler\TraceableRegistry;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\AI\McpBundle\Session\FrameworkSessionStore;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class McpBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/options.php');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $builder->setParameter('mcp.app', $config['app']);
        $builder->setParameter('mcp.version', $config['version']);
        $builder->setParameter('mcp.description', $config['description']);
        $builder->setParameter('mcp.website_url', $config['website_url']);
        $builder->setParameter('mcp.icons', $config['icons']);
        $builder->setParameter('mcp.pagination_limit', $config['pagination_limit']);
        $builder->setParameter('mcp.instructions', $config['instructions']);
        $builder->setParameter('mcp.discovery.scan_dirs', $config['discovery']['scan_dirs']);
        $builder->setParameter('mcp.discovery.exclude_dirs', $config['discovery']['exclude_dirs']);

        $this->registerMcpAttributes($builder);

        $builder->registerForAutoconfiguration(LoaderInterface::class)
            ->addTag('mcp.loader');

        $builder->registerForAutoconfiguration(RequestHandlerInterface::class)
            ->addTag('mcp.request_handler');

        $builder->registerForAutoconfiguration(NotificationHandlerInterface::class)
            ->addTag('mcp.notification_handler');

        if ($builder->getParameter('kernel.debug')) {
            $traceableRegistry = (new Definition('mcp.traceable_registry'))
                ->setClass(TraceableRegistry::class)
                ->setArguments([new Reference('.inner')])
                ->setDecoratedService('mcp.registry');
            $builder->setDefinition('mcp.traceable_registry', $traceableRegistry);

            $dataCollector = (new Definition(DataCollector::class))
                ->setArguments([new Reference('mcp.traceable_registry')])
                ->addTag('data_collector', ['id' => 'mcp']);
            $builder->setDefinition('mcp.data_collector', $dataCollector);
        }

        if (isset($config['client_transports'])) {
            $this->configureClient($config['client_transports'], $config['http'], $builder);
        }

        if ([] !== ($config['clients'] ?? [])) {
            $this->configureClients($config['clients'], $builder);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new McpPass());
    }

    private function registerMcpAttributes(ContainerBuilder $builder): void
    {
        $mcpAttributes = [
            McpTool::class => 'mcp.tool',
            McpPrompt::class => 'mcp.prompt',
            McpResource::class => 'mcp.resource',
            McpResourceTemplate::class => 'mcp.resource_template',
        ];

        foreach ($mcpAttributes as $attributeClass => $tag) {
            $builder->registerAttributeForAutoconfiguration(
                $attributeClass,
                static function (ChildDefinition $definition, object $attribute, \Reflector $reflector) use ($tag): void {
                    $definition->addTag($tag);
                }
            );
        }
    }

    /**
     * @param array{stdio: bool, http: bool}                                                                                      $transports
     * @param array{path: string, session: array{store: string, directory: string, cache_pool: string, prefix: string, ttl: int}} $httpConfig
     */
    private function configureClient(array $transports, array $httpConfig, ContainerBuilder $container): void
    {
        if (!$transports['stdio'] && !$transports['http']) {
            return;
        }

        // Register PSR factories
        $container->register('mcp.psr17_factory', Psr17Factory::class);

        $container->register('mcp.psr_http_factory', PsrHttpFactory::class)
            ->setArguments([
                new Reference('mcp.psr17_factory'),
                new Reference('mcp.psr17_factory'),
                new Reference('mcp.psr17_factory'),
                new Reference('mcp.psr17_factory'),
            ]);

        $container->register('mcp.http_foundation_factory', HttpFoundationFactory::class);

        // Configure session store based on HTTP config
        $this->configureSessionStore($httpConfig['session'], $container);

        if ($transports['stdio']) {
            $container->register('mcp.server.command', McpCommand::class)
                ->setArguments([
                    new Reference('mcp.server'),
                    new Reference('logger'),
                ])
                ->addTag('console.command')
                ->addTag('monolog.logger', ['channel' => 'mcp']);
        }

        if ($transports['http']) {
            $container->register('mcp.server.controller', McpController::class)
                ->setArguments([
                    new Reference('mcp.server'),
                    new Reference('mcp.psr_http_factory'),
                    new Reference('mcp.http_foundation_factory'),
                    new Reference('mcp.psr17_factory'),
                    new Reference('mcp.psr17_factory'),
                    new Reference('logger'),
                ])
                ->setPublic(true)
                ->addTag('controller.service_arguments')
                ->addTag('monolog.logger', ['channel' => 'mcp']);
        }

        $container->register('mcp.server.route_loader', RouteLoader::class)
            ->setArguments([
                $transports['http'],
                $httpConfig['path'],
            ])
            ->addTag('routing.loader');
    }

    /**
     * @param array<string, array{
     *     transport: 'stdio'|'http',
     *     command: list<string>,
     *     cwd: string|null,
     *     env: array<string, string>,
     *     url: string|null,
     *     headers: array<string, string>,
     *     http_client: string|null,
     *     client_info: array{name: string, version: string, description: string|null},
     *     init_timeout: int,
     *     request_timeout: int,
     * }> $clients
     */
    private function configureClients(array $clients, ContainerBuilder $container): void
    {
        foreach ($clients as $name => $config) {
            $transportId = 'mcp.client.'.$name.'.transport';

            if ('stdio' === $config['transport']) {
                $command = $config['command'];
                $program = array_shift($command);

                $transport = (new Definition(McpStdioTransport::class))
                    ->setArguments([
                        $program,
                        $command,
                        $config['cwd'],
                        [] !== $config['env'] ? $config['env'] : null,
                        new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                    ])
                    ->addTag('monolog.logger', ['channel' => 'mcp']);
            } else {
                $transport = (new Definition(McpHttpTransport::class))
                    ->setArguments([
                        $config['url'],
                        $config['headers'],
                        null !== $config['http_client'] ? new Reference($config['http_client']) : null,
                        null,
                        null,
                        new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                    ])
                    ->addTag('monolog.logger', ['channel' => 'mcp']);
            }

            $container->setDefinition($transportId, $transport);

            $clientInfo = (new Definition(Implementation::class))
                ->setArguments([
                    $config['client_info']['name'],
                    $config['client_info']['version'],
                    $config['client_info']['description'],
                ]);
            $container->setDefinition('mcp.client.'.$name.'.client_info', $clientInfo);

            $configuration = (new Definition(McpClientConfiguration::class))
                ->setArguments([
                    new Reference('mcp.client.'.$name.'.client_info'),
                    new Definition(ClientCapabilities::class),
                    null,
                    $config['init_timeout'],
                    $config['request_timeout'],
                ]);
            $container->setDefinition('mcp.client.'.$name.'.configuration', $configuration);

            $protocol = (new Definition(McpClientProtocol::class))
                ->setArguments([
                    [],
                    [],
                    null,
                    new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                ]);
            $container->setDefinition('mcp.client.'.$name.'.protocol', $protocol);

            $client = (new Definition(McpClient::class))
                ->setArguments([
                    new Reference('mcp.client.'.$name.'.protocol'),
                    new Reference('mcp.client.'.$name.'.configuration'),
                    new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('mcp.client', ['name' => $name])
                ->addTag('monolog.logger', ['channel' => 'mcp']);
            $container->setDefinition('mcp.client.'.$name, $client);
        }

        $container->register('mcp.client.locator', ServiceLocator::class)
            ->setArguments([array_combine(
                array_keys($clients),
                array_map(static fn (string $name): Reference => new Reference('mcp.client.'.$name), array_keys($clients)),
            )])
            ->addTag('container.service_locator');

        $container->register('mcp.client.debug_command', McpClientDebugCommand::class)
            ->setArguments([new Reference('mcp.client.locator')])
            ->addTag('console.command');
    }

    /**
     * @param array{store: string, directory: string, cache_pool: string, prefix: string, ttl: int} $sessionConfig
     */
    private function configureSessionStore(array $sessionConfig, ContainerBuilder $container): void
    {
        if ('memory' === $sessionConfig['store']) {
            $container->register('mcp.session.store', InMemorySessionStore::class)
                ->setArguments([$sessionConfig['ttl']]);
        } elseif ('cache' === $sessionConfig['store']) {
            $cachePoolId = $sessionConfig['cache_pool'];

            // Create the default cache pool as a PSR-16 wrapper around cache.app if it doesn't exist
            if ('cache.mcp.sessions' === $cachePoolId && !$container->hasDefinition($cachePoolId) && !$container->hasAlias($cachePoolId)) {
                $container->register($cachePoolId, Psr16Cache::class)
                    ->setArguments([new Reference('cache.app')]);
            }

            $container->register('mcp.session.store', Psr16SessionStore::class)
                ->setArguments([
                    new Reference($sessionConfig['cache_pool']),
                    $sessionConfig['prefix'],
                    $sessionConfig['ttl'],
                ]);
        } elseif ('framework' === $sessionConfig['store']) {
            $container->register('mcp.session.store', FrameworkSessionStore::class)
                ->setArguments([
                    new Reference('session.handler'),
                    $sessionConfig['prefix'],
                    $sessionConfig['ttl'],
                ]);
        } else {
            $container->register('mcp.session.store', FileSessionStore::class)
                ->setArguments([$sessionConfig['directory'], $sessionConfig['ttl']]);
        }
    }
}
