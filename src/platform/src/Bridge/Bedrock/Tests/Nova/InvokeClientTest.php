<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Nova;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use AsyncAws\BedrockRuntime\Input\InvokeModelRequest;
use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use AsyncAws\Core\Configuration;
use AsyncAws\Core\Test\ResultMockFactory;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\InvokeClient;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class InvokeClientTest extends TestCase
{
    private MockObject&BedrockRuntimeClient $bedrockClient;
    private InvokeClient $modelClient;
    private Nova $model;

    protected function setUp(): void
    {
        $this->model = new Nova('nova-pro');
        $this->bedrockClient = $this->getMockBuilder(BedrockRuntimeClient::class)
            ->setConstructorArgs([
                Configuration::create([Configuration::OPTION_REGION => Configuration::DEFAULT_REGION]),
            ])
            ->onlyMethods(['invokeModel'])
            ->getMock();
    }

    public function testPassesModelId()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('us.amazon.nova-pro-v1:0', $arg->getModelId());
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertJson($arg->getBody());

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new InvokeClient($this->bedrockClient);

        $response = $this->modelClient->request($this->model, ['message' => 'test']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testUnsetsModelName()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertJson($arg->getBody());

                $body = json_decode($arg->getBody(), true);
                $this->assertArrayNotHasKey('model', $body);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new InvokeClient($this->bedrockClient);

        $response = $this->modelClient->request($this->model, ['message' => 'test', 'model' => 'nova-pro']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testSetsToolOptionsIfToolsEnabled()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertJson($arg->getBody());

                $body = json_decode($arg->getBody(), true);
                $this->assertSame(['tools' => ['Tool']], $body['toolConfig']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new InvokeClient($this->bedrockClient);

        $options = [
            'tools' => ['Tool'],
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testPassesTemperature()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertJson($arg->getBody());

                $body = json_decode($arg->getBody(), true);
                $this->assertArrayHasKey('inferenceConfig', $body);
                $this->assertSame(['temperature' => 0.35], $body['inferenceConfig']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new InvokeClient($this->bedrockClient);

        $options = [
            'temperature' => 0.35,
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testPassesMaxTokens()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertJson($arg->getBody());

                $body = json_decode($arg->getBody(), true);
                $this->assertArrayHasKey('inferenceConfig', $body);
                $this->assertSame(['maxTokens' => 1000], $body['inferenceConfig']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new InvokeClient($this->bedrockClient);

        $options = [
            'max_tokens' => 1000,
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testPassesBothTemperatureAndMaxTokens()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertJson($arg->getBody());

                $body = json_decode($arg->getBody(), true);
                $this->assertArrayHasKey('inferenceConfig', $body);
                $this->assertSame(['temperature' => 0.35, 'maxTokens' => 1000], $body['inferenceConfig']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new InvokeClient($this->bedrockClient);

        $options = [
            'max_tokens' => 1000,
            'temperature' => 0.35,
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    #[TestDox('Supports Nova model')]
    public function testSupports()
    {
        $converter = new InvokeClient(new BedrockRuntimeClient());
        $this->assertTrue($converter->supports(new Nova('nova-pro')));
    }

    #[TestDox('Converts response with text content to TextResult')]
    public function testConvertTextResult()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [
                            [
                                'text' => 'Hello from Nova!',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new InvokeClient(new BedrockRuntimeClient());
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello from Nova!', $result->getContent());
    }

    #[TestDox('Converts response with tool use to ToolCallResult')]
    public function testConvertToolCallResult()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [
                            [
                                'text' => 'I will calculate this for you.',
                            ],
                            [
                                'toolUse' => [
                                    'toolUseId' => 'nova-tool-123',
                                    'name' => 'calculate',
                                    'input' => ['expression' => '2+2'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new InvokeClient(new BedrockRuntimeClient());
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('nova-tool-123', $toolCalls[0]->getId());
        $this->assertSame('calculate', $toolCalls[0]->getName());
        $this->assertSame(['expression' => '2+2'], $toolCalls[0]->getArguments());
    }

    #[TestDox('Converts response with multiple tool calls to ToolCallResult')]
    public function testConvertMultipleToolCalls()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [
                            [
                                'text' => 'I will help you with both requests.',
                            ],
                            [
                                'toolUse' => [
                                    'toolUseId' => 'nova-tool-1',
                                    'name' => 'get_weather',
                                    'input' => ['location' => 'New York'],
                                ],
                            ],
                            [
                                'toolUse' => [
                                    'toolUseId' => 'nova-tool-2',
                                    'name' => 'get_time',
                                    'input' => ['timezone' => 'EST'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new InvokeClient(new BedrockRuntimeClient());
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(2, $toolCalls);

        $this->assertSame('nova-tool-1', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'New York'], $toolCalls[0]->getArguments());

        $this->assertSame('nova-tool-2', $toolCalls[1]->getId());
        $this->assertSame('get_time', $toolCalls[1]->getName());
        $this->assertSame(['timezone' => 'EST'], $toolCalls[1]->getArguments());
    }

    #[TestDox('Prioritizes tool calls over text in mixed content')]
    public function testConvertMixedContentWithToolUse()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [
                            [
                                'text' => 'I will calculate this for you.',
                            ],
                            [
                                'toolUse' => [
                                    'toolUseId' => 'nova-tool-123',
                                    'name' => 'calculate',
                                    'input' => ['expression' => '5*10'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new InvokeClient(new BedrockRuntimeClient());
        $result = $converter->convert($rawResult);

        // When tool calls are present, should return ToolCallResult regardless of text
        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('nova-tool-123', $toolCalls[0]->getId());
    }

    #[TestDox('Throws RuntimeException when response has no output')]
    public function testConvertThrowsExceptionWhenNoOutput()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        $converter = new InvokeClient(new BedrockRuntimeClient());
        $converter->convert($rawResult);
    }

    #[TestDox('Throws RuntimeException when response has empty output')]
    public function testConvertThrowsExceptionWhenEmptyOutput()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        $converter = new InvokeClient(new BedrockRuntimeClient());
        $converter->convert($rawResult);
    }

    #[TestDox('Throws RuntimeException when content has no text')]
    public function testConvertThrowsExceptionWhenNoText()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [
                            [
                                'invalid' => 'data',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response content does not contain any text.');

        $converter = new InvokeClient(new BedrockRuntimeClient());
        $converter->convert($rawResult);
    }

    #[TestDox('Throws RuntimeException when message structure is missing')]
    public function testConvertThrowsExceptionWhenMissingMessageStructure()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response content does not contain any text.');

        $converter = new InvokeClient(new BedrockRuntimeClient());
        $converter->convert($rawResult);
    }

    #[TestDox('Throws RuntimeException when content array is missing')]
    public function testConvertThrowsExceptionWhenMissingContent()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response content does not contain any text.');

        $converter = new InvokeClient(new BedrockRuntimeClient());
        $converter->convert($rawResult);
    }
}
