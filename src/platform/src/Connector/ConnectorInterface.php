<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Connector;

use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\ConnectorException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\ResultInterface as ConverterResult;
use Symfony\AI\Platform\Result\StreamResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ConnectorInterface
{
    public function getContract(): Contract;

    public function getModelCatalog(): ModelCatalogInterface;

    /**
     * @param array<int|string, mixed>|string $payload
     * @param array<string, mixed>            $options
     *
     * @return ResultPromise
     */
    public function invoke(Model $model, array|string $payload, array $options): ResultPromise;

    public function isError(ResultInterface $result): bool;

    public function handleStream(Model $model, ResultInterface $result, array $options): StreamResult;

    /**
     * @throws ConnectorException
     */
    public function handleError(Model $model, ResultInterface $result): never;

    public function handleResult(Model $model, ResultInterface $result, array $options): ConverterResult;
}
