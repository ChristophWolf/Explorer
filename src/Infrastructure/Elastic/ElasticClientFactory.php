<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Infrastructure\Elastic;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client as HttpClient;

final class ElasticClientFactory
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function client(): Client
    {
        return $this->client;
    }

    public static function fake(Response $response): ElasticClientFactory
    {
        $handler = new MockHandler([$response]);
        $handlerStack = HandlerStack::create($handler);
        $client = new HttpClient(['handler' => $handlerStack]);
        $builder = ClientBuilder::create();
        $builder->setHosts(['testhost']);
        $builder->setHttpClient($client);
        return new self($builder->build());
    }
}
