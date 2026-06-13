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

use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Video;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Describes what a model must be able to do, for capability-based model selection.
 *
 * A requirement is satisfied by a {@see Model} when every required task, input
 * modality, output modality and feature is present in the model's declared capabilities.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelRequirements
{
    /**
     * @var list<Task>
     */
    private readonly array $tasks;

    /**
     * @var list<Modality>
     */
    private readonly array $inputModalities;

    /**
     * @var list<Modality>
     */
    private readonly array $outputModalities;

    /**
     * @var list<Feature>
     */
    private readonly array $features;

    /**
     * @param Task[]     $tasks
     * @param Modality[] $inputModalities
     * @param Modality[] $outputModalities
     * @param Feature[]  $features
     */
    public function __construct(
        array $tasks = [],
        array $inputModalities = [],
        array $outputModalities = [],
        array $features = [],
    ) {
        $this->tasks = array_values($tasks);
        $this->inputModalities = array_values($inputModalities);
        $this->outputModalities = array_values($outputModalities);
        $this->features = array_values($features);
    }

    /**
     * Infers the requirements from a conversation and invocation options.
     *
     * Input modalities are derived from the message content (an image part requires image
     * input, an audio part requires audio input, ...), features from the options (tool
     * definitions require tool calling, a response format requires structured output, ...),
     * and the output modalities default to the ones implied by the given task.
     *
     * @param array<string, mixed> $options
     */
    public static function fromInput(MessageBag $messages, array $options = [], Task $task = Task::TEXT_GENERATION): self
    {
        /** @var array<string, Modality> $input */
        $input = [];
        foreach ($messages->getMessages() as $message) {
            $content = $message->getContent();
            if (!\is_array($content)) {
                $input[Modality::TEXT->value] = Modality::TEXT;
                continue;
            }

            foreach ($content as $part) {
                $modality = self::contentModality($part);
                if (null !== $modality) {
                    $input[$modality->value] = $modality;
                }
            }
        }

        $features = [];
        if (isset($options['tools']) && [] !== $options['tools']) {
            $features[] = Feature::TOOL_CALLING;
        }
        if (isset($options['response_format'])) {
            $features[] = Feature::STRUCTURED_OUTPUT;
        }
        if (true === ($options['stream'] ?? false)) {
            $features[] = Feature::STREAMING;
        }

        return new self([$task], array_values($input), self::outputModalitiesForTask($task), $features);
    }

    public function isSatisfiedBy(Model $model): bool
    {
        return $model->satisfies($this);
    }

    /**
     * @return list<Task>
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * @return list<Modality>
     */
    public function getInputModalities(): array
    {
        return $this->inputModalities;
    }

    /**
     * @return list<Modality>
     */
    public function getOutputModalities(): array
    {
        return $this->outputModalities;
    }

    /**
     * @return list<Feature>
     */
    public function getFeatures(): array
    {
        return $this->features;
    }

    private static function contentModality(ContentInterface $content): ?Modality
    {
        if ($content instanceof Text) {
            return Modality::TEXT;
        }
        if ($content instanceof Image || $content instanceof ImageUrl) {
            return Modality::IMAGE;
        }
        if ($content instanceof Audio) {
            return Modality::AUDIO;
        }
        if ($content instanceof Document || $content instanceof DocumentUrl) {
            return Modality::PDF;
        }
        if ($content instanceof Video) {
            return Modality::VIDEO;
        }

        return null;
    }

    /**
     * @return list<Modality>
     */
    private static function outputModalitiesForTask(Task $task): array
    {
        return match ($task) {
            Task::IMAGE_GENERATION => [Modality::IMAGE],
            Task::VIDEO_GENERATION => [Modality::VIDEO],
            Task::SPEECH_SYNTHESIS => [Modality::AUDIO],
            Task::EMBEDDING, Task::RERANKING => [],
            Task::TEXT_GENERATION, Task::TRANSCRIPTION => [Modality::TEXT],
        };
    }
}

