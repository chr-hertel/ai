<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DockerModelRunner\Transport;

use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport as GenericHttpTransport;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport extends GenericHttpTransport
{
    protected function throwOnContextOverflow(ResponseInterface $response): void
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return;
        }

        if ('exceed_context_size_error' === ($data['error']['type'] ?? null)) {
            throw new ExceedContextSizeException($data['error']['message'] ?? 'Context size exceeded');
        }

        parent::throwOnContextOverflow($response);
    }
}
