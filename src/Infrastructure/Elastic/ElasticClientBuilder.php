<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Infrastructure\Elastic;

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Transport\NodePool\NodePoolInterface;
use Illuminate\Contracts\Config\Repository;

final class ElasticClientBuilder
{
    private const HOST_KEYS = ['host', 'port', 'scheme'];

    public static function fromConfig(Repository $config): ClientBuilder
    {
        $builder = ClientBuilder::create();

        $hostConnectionProperties = $config->get('explorer.connection.hosts', []);

        if (is_array($hostConnectionProperties) && !empty($hostConnectionProperties)) {
            $builder->setHosts($hostConnectionProperties);
        }

        if ($config->has('explorer.additionalConnections')) {
            $builder->setHosts([...$config->get('explorer.connection.hosts'), ...$config->get('explorer.additionalConnections')]);
        }
        if ($config->has('explorer.connection.pool') && $config->get('explorer.connection.pool') instanceof NodePoolInterface) {
            $builder->setNodePool($config->get('explorer.connection.pool'));
        }

        if ($config->has('explorer.connection.api')) {
            $builder->setApiKey(
                $config->get('explorer.connection.api.id'),
                $config->get('explorer.connection.api.key')
            );
        }

        if ($config->has('explorer.connection.elasticCloudId')) {
            $builder->setElasticCloudId(
                $config->get('explorer.connection.elasticCloudId'),
            );
        }

        if ($config->has('explorer.connection.auth')) {
            $builder->setBasicAuthentication(
                $config->get('explorer.connection.auth.username'),
                $config->get('explorer.connection.auth.password')
            );
        }

        if ($config->has('explorer.connection.ssl.verify')) {
            $builder->setSSLVerification($config->get('explorer.connection.ssl.verify'));
        }

        if ($config->has('explorer.connection.ssl.key')) {
            [$path, $password] = self::getPathAndPassword($config->get('explorer.connection.ssl.key'));
            $builder->setSSLKey($path, $password);
        }

        if ($config->has('explorer.connection.ssl.cert')) {
            [$path, $password] = self::getPathAndPassword($config->get('explorer.connection.ssl.cert'));
            $builder->setSSLCert($path, $password);
        }

        return $builder;
    }

    /**
     * @param array|string $config
     */
    private static function getPathAndPassword(mixed $config): array
    {
        return is_array($config) ? $config : [$config, null];
    }
}
