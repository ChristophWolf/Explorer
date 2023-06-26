<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Infrastructure\Elastic;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Http\Promise\Promise;
use JeroenG\Explorer\Application\DocumentAdapterInterface;
use JeroenG\Explorer\Application\Operations\Bulk\BulkOperationInterface;
use JeroenG\Explorer\Application\Results;
use JeroenG\Explorer\Application\SearchCommandInterface;

final class ElasticDocumentAdapter implements DocumentAdapterInterface
{
    public function __construct(
        private Client $client,
    ) {
    }

    public function bulk(BulkOperationInterface $command): Elasticsearch | Promise
    {
        return $this->client->bulk([
            'body' => $command->build(),
        ]);
    }

    public function update(string $index, $id, array $data): Elasticsearch | Promise
    {
        return $this->client->index([
            'index' => $index,
            'id' => $id,
            'body' => $data,
        ]);
    }

    public function delete(string $index, $id): void
    {
        try {
            $this->client->delete([
                'index' => $index,
                'id' => $id
            ]);
        } catch (ElasticsearchException $e) {
            $this->client->getLogger()->error("Failed to delete document", ['index' => $index, 'id' => $id, 'reason' => $e->getMessage()]);
        }
    }

    public function search(SearchCommandInterface $command): Results
    {
        return (new Finder($this->client, $command))->find();
    }
}
