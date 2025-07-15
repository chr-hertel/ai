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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Connector\ConnectorInterface;
use Symfony\AI\Platform\Connector\ResultInterface as RawResultInterface;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ResultPromise;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class ConnectorPlatform /*implements PlatformInterface*/
{
    public function __construct(
        private ConnectorInterface $connector,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public function invoke(string $model, array|string|object $input, array $options = []): ResultPromise
    {
        $model = $this->connector->getModelCatalog()->getModel($model);

        $event = new InvocationEvent($model, $input, $options);
        $this->eventDispatcher?->dispatch($event);

        $contract = $this->connector->getContract();
        $payload = $contract->createRequestPayload($event->getModel(), $event->getInput());
        $options = array_merge($model->getOptions(), $event->getOptions());

        if (isset($options['tools'])) {
            $options['tools'] = $contract->createToolOption($options['tools'], $model);
        }

        $promise = $this->connector->invoke($model, $payload, $options);
        $promise->registerConverter($this->convertResult(...));

        $event = new ResultEvent($model, $promise, $options);
        $this->eventDispatcher?->dispatch($event);

        return $event->getDeferredResult();
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->connector->getModelCatalog();
    }

    private function convertResult(RawResultInterface $result, array $options): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return $this->connector->handleStream($options['model'], $result, $options);
        }

        if ($this->connector->isError($result)) {
            $this->connector->handleError($options['model'], $result);
        }

        return $this->connector->handleResult($options['model'], $result, $options);
    }
}
