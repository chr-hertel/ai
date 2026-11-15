<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert;

use Symfony\AI\Platform\Bridge\Generic\ChatCompletionsClient as GenericChatCompletionsClient;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;

/**
 * Albert reports the environmental impacts of a call next to the token usage, as estimated
 * energy consumption ("kWh") and greenhouse gas emission ("kgCO2eq"). It is exposed as the
 * "impacts" result metadata, in the shape the API returns it:
 *
 *     ['kWh' => float, 'kgCO2eq' => float]
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChatCompletionsClient extends GenericChatCompletionsClient
{
    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        $converted = parent::convert($raw, $options);

        // A streamed footprint is only known once the stream is consumed and arrives as a metadata delta.
        if ($options['stream'] ?? false) {
            return $converted;
        }

        if (null !== $impacts = $raw->getData()['usage']['impacts'] ?? null) {
            $converted->getMetadata()->add('impacts', $impacts);
        }

        return $converted;
    }

    protected function yieldChunkMetadata(array $data): \Generator
    {
        if (isset($data['usage']['impacts'])) {
            yield new MetadataDelta('impacts', $data['usage']['impacts']);
        }
    }
}
