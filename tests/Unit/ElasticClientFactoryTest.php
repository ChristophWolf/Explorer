<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Tests\Unit;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use GuzzleHttp\Psr7\Response;
use JeroenG\Explorer\Infrastructure\Elastic\ElasticClientFactory;
use JeroenG\Explorer\Infrastructure\Elastic\Finder;
use JeroenG\Explorer\Infrastructure\Scout\ScoutSearchCommandBuilder;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class ElasticClientFactoryTest extends MockeryTestCase
{
    public function testCreateClientWithFakeResponse(): void
    {
        $file = fopen(__DIR__ . '/../Support/fakeresponse.json', 'rb');
        $factory = ElasticClientFactory::fake(new Response(200, body: $file));

        self::assertEquals('testhost', $factory->client()->getTransport()->getNodePool()->nextNode()->getUri()->getHost());
        self::assertNotInstanceOf(Mockery\MockInterface::class, $factory->client());
    }

    public function testMakeFakeCall(): void
    {
        $file = fopen(__DIR__ . '/../Support/fakeresponse.json', 'rb');
        $headers = [
            Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
            'Content-Type' => 'application/json'
        ];
        $factory = ElasticClientFactory::fake(new Response(200, $headers, $file));
        $cmd = new ScoutSearchCommandBuilder();
        $cmd->setIndex('test');
        $finder = new Finder($factory->client(), $cmd);
        $results = $finder->find();

        self::assertEquals(2, $results->count());
    }
}
