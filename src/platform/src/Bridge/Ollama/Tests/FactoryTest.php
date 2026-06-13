<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\Factory;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Component\HttpClient\MockHttpClient;

final class FactoryTest extends TestCase
{
    public function testCreatePlatformUsesGivenModelCatalog()
    {
        $model = new Ollama('llama3.2');

        $catalog = $this->createMock(ModelCatalogInterface::class);
        $catalog->expects($this->once())
            ->method('getModel')
            ->with('llama3.2')
            ->willReturn($model);

        $platform = Factory::createPlatform('http://127.0.0.1:11434', httpClient: new MockHttpClient(), modelCatalog: $catalog);

        $this->assertSame($model, $platform->getModelCatalog()->getModel('llama3.2'));
    }
}
