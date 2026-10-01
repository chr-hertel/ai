<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge;

/**
 * Masks sensitive data, e.g. email addresses or card numbers, before captured content is recorded on a span.
 *
 * Only applies with content capture enabled. It receives the serialized attribute value, so a redactor should
 * replace matches with plain placeholders to keep JSON values intact.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ContentRedactorInterface
{
    public function redact(string $content): string;
}
