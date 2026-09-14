<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\UniversalAi;

use Symfony\AI\Platform\Bridge\EdenAi\ResultMetadataTrait;
use Symfony\AI\Platform\Bridge\EdenAi\Tts;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Eden AI returns the synthesized audio as a temporary resource URL, which is downloaded
 * to expose the audio content as a BinaryResult.
 *
 * Because that download is the happy path of every text-to-speech call, its own failures
 * are mapped onto platform exceptions too: the URL is signed and short-lived, so it can
 * expire or be rejected independently of the call that produced it.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class TtsClient extends AbstractUniversalAiClient
{
    use ResultMetadataTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Tts;
    }

    public function convert(RawResultInterface $result, array $options = []): BinaryResult
    {
        $data = $this->getResponseData($result);

        if (!isset($data['output']['audio_resource_url']) || !\is_string($data['output']['audio_resource_url'])) {
            throw new RuntimeException('Response does not contain audio_resource_url.');
        }

        $audioResult = $this->download($data['output']['audio_resource_url']);

        $this->attachEdenAiMetadata($audioResult, $data);

        return $audioResult;
    }

    /**
     * The signed URL is deliberately kept out of the exception messages: it grants access
     * to the generated audio and would leak through logs.
     */
    private function download(string $audioResourceUrl): BinaryResult
    {
        try {
            $audioResponse = $this->httpClient->request('GET', $audioResourceUrl);
            $statusCode = $audioResponse->getStatusCode();

            if (200 !== $statusCode) {
                throw new RuntimeException(\sprintf('Could not download the synthesized audio from Eden AI, got HTTP %d.', $statusCode));
            }

            return new BinaryResult(
                $audioResponse->getContent(),
                $this->resolveMimeType($audioResourceUrl, $audioResponse->getHeaders()['content-type'][0] ?? null),
            );
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('Could not download the synthesized audio from Eden AI.', 0, $e);
        }
    }

    /**
     * The CDN serving the audio answers "binary/octet-stream" whatever the format really
     * is, so the extension Eden AI puts in the resource URL is the more reliable signal:
     * it follows the requested "audio_format" option ("..._.wav" for wav, "..._.mp3"
     * otherwise). A specific content-type still wins, should the CDN ever send one.
     */
    private function resolveMimeType(string $audioResourceUrl, ?string $contentType): string
    {
        if (null !== $contentType && '' !== $contentType && !$this->isGenericContentType($contentType)) {
            return $contentType;
        }

        $extension = strtolower(pathinfo(parse_url($audioResourceUrl, \PHP_URL_PATH) ?: '', \PATHINFO_EXTENSION));

        return match ($extension) {
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/opus',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            'm4a', 'mp4' => 'audio/mp4',
            'webm' => 'audio/webm',
            default => 'audio/mpeg',
        };
    }

    private function isGenericContentType(string $contentType): bool
    {
        $type = strtolower(trim(explode(';', $contentType)[0]));

        return \in_array($type, ['binary/octet-stream', 'application/octet-stream', 'application/binary'], true);
    }
}
