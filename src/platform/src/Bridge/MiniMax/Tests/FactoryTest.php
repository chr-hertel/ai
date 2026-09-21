<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\MiniMax\Factory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FactoryTest extends TestCase
{
    public function testTheJobHandleCarriesTheNameTheProviderWasCreatedWith()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['task_id' => '789', 'base_resp' => ['status_code' => 0]]));

        $handle = Factory::createProvider('key', $httpClient, name: 'minimax-eu')
            ->invoke('MiniMax-Hailuo-02', 'A cat playing piano')
            ->asJob();

        $this->assertSame('789', $handle->getId());
        $this->assertSame('minimax-eu', $handle->getProvider());
    }

    public function testTheJobClientCreatesHandlesForTheGivenName()
    {
        $this->assertSame('minimax-eu', Factory::createJobClient('key', name: 'minimax-eu')->createHandle('789', [], 600)->getProvider());
    }
}
