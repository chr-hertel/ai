<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string<Model>, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'MiniMax-M2' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'MiniMax-M2.1' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'MiniMax-M2.5' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'MiniMax-M2.7' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'MiniMax-M3' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'speech-2.8-hd' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                    Capability::TEXT_TO_SPEECH_ASYNC,
                ],
            ],
            'speech-2.8-turbo' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                    Capability::TEXT_TO_SPEECH_ASYNC,
                ],
            ],
            'speech-2.6-hd' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                    Capability::TEXT_TO_SPEECH_ASYNC,
                ],
            ],
            'speech-2.6-turbo' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                    Capability::TEXT_TO_SPEECH_ASYNC,
                ],
            ],
            'speech-02-hd' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                    Capability::TEXT_TO_SPEECH_ASYNC,
                ],
            ],
            'speech-02-turbo' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                    Capability::TEXT_TO_SPEECH_ASYNC,
                ],
            ],
            'speech-01-hd' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                    Capability::TEXT_TO_SPEECH_ASYNC,
                ],
            ],
            'speech-01-turbo' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'MiniMax-Hailuo-2.3' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_VIDEO,
                    Capability::IMAGE_TO_VIDEO,
                ],
            ],
            'MiniMax-Hailuo-2.3-Fast' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::IMAGE_TO_VIDEO,
                ],
            ],
            'MiniMax-Hailuo-02' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_VIDEO,
                    Capability::IMAGE_TO_VIDEO,
                    Capability::VIDEO_FRAME_TO_FRAME,
                ],
            ],
            'T2V-01-Director' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_VIDEO,
                    Capability::IMAGE_TO_VIDEO,
                ],
            ],
            'I2V-01-live' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::IMAGE_TO_VIDEO,
                ],
            ],
            'T2V-01' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_VIDEO,
                    Capability::IMAGE_TO_VIDEO,
                ],
            ],
            'S2V-01' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::VIDEO_WITH_SUBJECT,
                ],
            ],
            'image-01' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::TEXT_TO_IMAGE,
                    Capability::IMAGE_TO_IMAGE,
                ],
            ],
            'image-01-live' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::IMAGE_TO_IMAGE,
                ],
            ],
            'music-1.5' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::MUSIC,
                ],
            ],
            'music-2.6' => [
                'class' => MiniMax::class,
                'capabilities' => [
                    Capability::MUSIC,
                ],
            ],
        ];

        $this->models = [
            ...$defaultModels,
            ...$additionalModels,
        ];
    }

    protected function endpointsForModel(array $modelConfig): array
    {
        $capabilities = $modelConfig['capabilities'];

        $supports = static fn (Capability $capability): bool => \in_array($capability, $capabilities, true);

        return match (true) {
            $supports(Capability::INPUT_MESSAGES) => [new Endpoint(ChatCompletionsClient::ENDPOINT)],
            $supports(Capability::TEXT_TO_SPEECH) => [new Endpoint(SpeechClient::ENDPOINT)],
            $supports(Capability::TEXT_TO_IMAGE),
            $supports(Capability::IMAGE_TO_IMAGE) => [new Endpoint(ImageClient::ENDPOINT)],
            $supports(Capability::MUSIC) => [new Endpoint(MusicClient::ENDPOINT)],
            $supports(Capability::TEXT_TO_VIDEO),
            $supports(Capability::IMAGE_TO_VIDEO),
            $supports(Capability::VIDEO_WITH_SUBJECT) => [new Endpoint(VideoClient::ENDPOINT)],
            default => [],
        };
    }
}
