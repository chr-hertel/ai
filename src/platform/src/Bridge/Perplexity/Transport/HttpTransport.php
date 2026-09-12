<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Transport;

use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport as GenericHttpTransport;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Perplexity speaks the OpenAI-compatible wire format but reports a context
 * overflow with its own error type and wording, so only that detection differs
 * from the shared transport.
 *
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

        $error = $data['error'] ?? [];
        $message = $error['message'] ?? '';

        if ('too_many_prompt_tokens' === ($error['type'] ?? null) || str_contains(strtolower($message), 'too long')) {
            throw new ExceedContextSizeException('' !== $message ? $message : 'Context size exceeded');
        }

        parent::throwOnContextOverflow($response);
    }
}
