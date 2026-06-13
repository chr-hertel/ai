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
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\EndpointHandlerInterface;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ProviderTest extends TestCase
{
    public function testGetName()
    {
        $provider = new Provider(
            'openai',
            [],
            [],
            $this->createStub(ModelCatalogInterface::class),
        );

        $this->assertSame('openai', $provider->getName());
    }

    public function testSupportsReturnsTrueWhenCatalogHasModel()
    {
        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn(new Model('gpt-4o', [Capability::INPUT_MESSAGES]));

        $provider = new Provider('openai', [], [], $catalog);

        $this->assertTrue($provider->supports('gpt-4o'));
    }

    public function testSupportsReturnsFalseWhenCatalogDoesNotHaveModel()
    {
        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willThrowException(
            new \Symfony\AI\Platform\Exception\ModelNotFoundException('Model not found'),
        );

        $provider = new Provider('openai', [], [], $catalog);

        $this->assertFalse($provider->supports('unknown-model'));
    }

    public function testSupportsWithModelObjectReturnsTrueWhenAModelClientSupportsIt()
    {
        $model = new Model('custom-model', [Capability::INPUT_MESSAGES]);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);

        $provider = new Provider('openai', [$modelClient], [], $this->createStub(ModelCatalogInterface::class));

        $this->assertTrue($provider->supports($model));
    }

    public function testSupportsWithModelObjectReturnsFalseWhenNoModelClientSupportsIt()
    {
        $model = new Model('custom-model', [Capability::INPUT_MESSAGES]);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(false);

        $provider = new Provider('openai', [$modelClient], [], $this->createStub(ModelCatalogInterface::class));

        $this->assertFalse($provider->supports($model));
    }

    public function testInvokeWithModelObjectSkipsCatalogResolution()
    {
        $model = new Model('custom-model', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]);
        $rawResult = $this->createStub(RawResultInterface::class);

        $catalog = $this->createMock(ModelCatalogInterface::class);
        $catalog->expects($this->never())->method('getModel');

        $modelClient = $this->createMock(ModelClientInterface::class);
        $modelClient->method('supports')->with($model)->willReturn(true);
        $modelClient->expects($this->once())
            ->method('request')
            ->with($model)
            ->willReturn($rawResult);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(true);
        $resultConverter->method('convert')->willReturn(new TextResult('Hello'));

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog);

        $result = $provider->invoke($model, 'Hello');

        $this->assertInstanceOf(DeferredResult::class, $result);
    }

    public function testInvokeUsesEndpointHandlerWhenSupported()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);
        $textResult = new TextResult('Hello');

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createMock(ModelClientInterface::class);
        $modelClient->expects($this->never())->method('request');

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->never())->method('convert');

        $endpointHandler = $this->createMock(EndpointHandlerInterface::class);
        $endpointHandler->method('supports')->willReturn(true);
        $endpointHandler->expects($this->once())
            ->method('request')
            ->with($model, 'Hello')
            ->willReturn($textResult);

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog, null, null, [$endpointHandler]);

        $result = $provider->invoke('gpt-4o', 'Hello');

        $this->assertInstanceOf(DeferredResult::class, $result);
        $this->assertSame($textResult, $result->getResult());
    }

    public function testInvokeRoutesEndpointHandlerByTask()
    {
        $model = new Model('whisper-1', [Capability::INPUT_AUDIO]);
        $translationResult = new TextResult('Bonjour');

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $transcription = $this->createMock(EndpointHandlerInterface::class);
        $transcription->method('supports')->willReturnCallback(static fn (Model $m, ?string $task = null): bool => null === $task || 'transcription' === $task);
        $transcription->expects($this->never())->method('request');

        $translation = $this->createMock(EndpointHandlerInterface::class);
        $translation->method('supports')->willReturnCallback(static fn (Model $m, ?string $task = null): bool => 'translation' === $task);
        $translation->expects($this->once())->method('request')->willReturn($translationResult);

        $provider = new Provider('openai', [], [], $catalog, null, null, [$transcription, $translation]);

        $result = $provider->invoke('whisper-1', ['audio'], ['task' => 'translation']);

        $this->assertSame($translationResult, $result->getResult());
    }

    public function testInvokeFallsBackToLegacyWhenNoEndpointHandlerMatches()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);
        $rawResult = $this->createStub(RawResultInterface::class);
        $textResult = new TextResult('Hello');

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createMock(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->expects($this->once())->method('request')->willReturn($rawResult);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(true);
        $resultConverter->method('convert')->willReturn($textResult);

        $endpointHandler = $this->createMock(EndpointHandlerInterface::class);
        $endpointHandler->method('supports')->willReturn(false);
        $endpointHandler->expects($this->never())->method('request');

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog, null, null, [$endpointHandler]);

        $result = $provider->invoke('gpt-4o', 'Hello');

        $this->assertSame($textResult, $result->getResult());
    }

    public function testSupportsModelObjectViaEndpointHandler()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);

        $endpointHandler = $this->createStub(EndpointHandlerInterface::class);
        $endpointHandler->method('supports')->willReturn(true);

        $provider = new Provider('openai', [], [], $this->createStub(ModelCatalogInterface::class), null, null, [$endpointHandler]);

        $this->assertTrue($provider->supports($model));
    }

    public function testInvokeResolvesModelAndDelegates()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]);
        $rawResult = $this->createStub(RawResultInterface::class);
        $textResult = new TextResult('Hello');

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->method('request')->willReturn($rawResult);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(true);
        $resultConverter->method('convert')->willReturn($textResult);

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog);

        $result = $provider->invoke('gpt-4o', 'Hello');

        $this->assertInstanceOf(DeferredResult::class, $result);
    }

    public function testInvokeThrowsWhenNoModelClientSupportsModel()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(false);

        $provider = new Provider('openai', [$modelClient], [], $catalog);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No ModelClient registered/');

        $provider->invoke('gpt-4o', 'Hello');
    }

    public function testInvokeThrowsWhenNoResultConverterSupportsModel()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);
        $rawResult = $this->createStub(RawResultInterface::class);

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->method('request')->willReturn($rawResult);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(false);

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No ResultConverter registered/');

        $provider->invoke('gpt-4o', 'Hello');
    }

    public function testInvokeDispatchesEvents()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);
        $rawResult = $this->createStub(RawResultInterface::class);
        $textResult = new TextResult('Hello');

        $catalog = $this->createStub(ModelCatalogInterface::class);
        $catalog->method('getModel')->willReturn($model);

        $modelClient = $this->createStub(ModelClientInterface::class);
        $modelClient->method('supports')->willReturn(true);
        $modelClient->method('request')->willReturn($rawResult);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('supports')->willReturn(true);
        $resultConverter->method('convert')->willReturn($textResult);

        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;

                return $event;
            });

        $provider = new Provider('openai', [$modelClient], [$resultConverter], $catalog, null, $eventDispatcher);

        $provider->invoke('gpt-4o', 'Hello');

        $this->assertCount(2, $dispatchedEvents);
        $this->assertInstanceOf(InvocationEvent::class, $dispatchedEvents[0]);
        $this->assertInstanceOf(ResultEvent::class, $dispatchedEvents[1]);
    }

    public function testGetModelCatalog()
    {
        $catalog = $this->createStub(ModelCatalogInterface::class);
        $provider = new Provider('openai', [], [], $catalog);

        $this->assertSame($catalog, $provider->getModelCatalog());
    }
}
