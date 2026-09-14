<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Transport;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface TransportInterface
{
    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     */
    public function send(Model $model, string $path, array $payload, array $headers = []): RawResultInterface;

    /**
     * @param array<string, mixed> $options
     */
    public function throwOnError(RawResultInterface $result, array $options = []): void;
}
