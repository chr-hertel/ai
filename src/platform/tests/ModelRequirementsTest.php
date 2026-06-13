<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\ModelRequirements;
use Symfony\AI\Platform\Task;

final class ModelRequirementsTest extends TestCase
{
    public function testFromInputInfersTextOnlyByDefault()
    {
        $requirements = ModelRequirements::fromInput(new MessageBag(
            Message::forSystem('You are helpful.'),
            Message::ofUser('Hello'),
        ));

        $this->assertSame([Task::TEXT_GENERATION], $requirements->getTasks());
        $this->assertSame([Modality::TEXT], $requirements->getInputModalities());
        $this->assertSame([Modality::TEXT], $requirements->getOutputModalities());
        $this->assertSame([], $requirements->getFeatures());
    }

    public function testFromInputInfersImageInputFromContent()
    {
        $requirements = ModelRequirements::fromInput(new MessageBag(
            Message::ofUser('Describe this', Image::fromDataUrl('data:image/png;base64,AAAA')),
        ));

        $this->assertEqualsCanonicalizing([Modality::TEXT, Modality::IMAGE], $requirements->getInputModalities());
    }

    public function testFromInputInfersFeaturesFromOptions()
    {
        $requirements = ModelRequirements::fromInput(
            new MessageBag(Message::ofUser('Hi')),
            ['tools' => ['some_tool'], 'response_format' => ['type' => 'json'], 'stream' => true],
        );

        $this->assertEqualsCanonicalizing(
            [Feature::TOOL_CALLING, Feature::STRUCTURED_OUTPUT, Feature::STREAMING],
            $requirements->getFeatures(),
        );
    }

    public function testFromInputUsesGivenTaskForOutputModalities()
    {
        $requirements = ModelRequirements::fromInput(new MessageBag(Message::ofUser('A cat')), [], Task::IMAGE_GENERATION);

        $this->assertSame([Task::IMAGE_GENERATION], $requirements->getTasks());
        $this->assertSame([Modality::IMAGE], $requirements->getOutputModalities());
    }

    public function testEmptyToolsDoesNotRequireToolCalling()
    {
        $requirements = ModelRequirements::fromInput(new MessageBag(Message::ofUser('Hi')), ['tools' => []]);

        $this->assertNotContains(Feature::TOOL_CALLING, $requirements->getFeatures());
    }
}
