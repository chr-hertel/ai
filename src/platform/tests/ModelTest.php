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
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Task;

final class ModelTest extends TestCase
{
    public function testReturnsName()
    {
        $model = new Model('gpt-4');

        $this->assertSame('gpt-4', $model->getName());
    }

    public function testReturnsCapabilities()
    {
        $model = new Model('gpt-4', [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING]);

        $this->assertSame([Task::TEXT_GENERATION], $model->getTasks());
        $this->assertSame([Modality::TEXT], $model->getInputModalities());
        $this->assertSame([Modality::TEXT], $model->getOutputModalities());
        $this->assertSame([Feature::TOOL_CALLING], $model->getFeatures());
    }

    public function testChecksCapabilityMembership()
    {
        $model = new Model('gpt-4', [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING]);

        $this->assertTrue($model->handles(Task::TEXT_GENERATION));
        $this->assertFalse($model->handles(Task::EMBEDDING));
        $this->assertTrue($model->accepts(Modality::TEXT));
        $this->assertFalse($model->accepts(Modality::IMAGE));
        $this->assertTrue($model->produces(Modality::TEXT));
        $this->assertTrue($model->has(Feature::TOOL_CALLING));
        $this->assertFalse($model->has(Feature::STREAMING));
    }

    public function testIsMultimodalInput()
    {
        $this->assertTrue((new Model('m', [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE]))->isMultimodalInput());
        $this->assertFalse((new Model('m', [Task::TEXT_GENERATION], [Modality::TEXT]))->isMultimodalInput());
    }

    public function testReturnsEmptyCapabilitiesByDefault()
    {
        $model = new Model('gpt-4');

        $this->assertSame([], $model->getTasks());
        $this->assertSame([], $model->getInputModalities());
        $this->assertSame([], $model->getOutputModalities());
        $this->assertSame([], $model->getFeatures());
    }

    public function testReturnsOptions()
    {
        $options = [
            'temperature' => 0.7,
            'max_tokens' => 1024,
        ];
        $model = new Model('gpt-4', [], [], [], [], $options);

        $this->assertSame($options, $model->getOptions());
    }

    public function testReturnsEmptyOptionsByDefault()
    {
        $model = new Model('gpt-4');

        $this->assertSame([], $model->getOptions());
    }
}
