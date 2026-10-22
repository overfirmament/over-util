<?php

namespace Overfirmament\OverUtils\ToolBox;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;

/**
 * Caches AMQP connections per configured connection name.
 *
 * Channels remain short lived: callers create a channel, publish or consume,
 * and close the channel. The underlying connection is reused so a RabbitMqUtil
 * object can safely be created inside a foreach loop.
 */
class RabbitMqConnectionManager
{
    /** @var array<string, AMQPStreamConnection> */
    private array $connections = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function connection(string $name, array $config): AMQPStreamConnection
    {
        $connection = $this->connections[$name] ?? null;

        if ($connection instanceof AMQPStreamConnection) {
            try {
                $connection->checkHeartBeat();

                if ($connection->isConnected()) {
                    return $connection;
                }
            } catch (Throwable) {
                // The connection is stale; replace it below.
            }

            $this->disconnect($name);
        }

        $connection = $this->create($config);
        $this->connections[$name] = $connection;

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function channel(string $name, array $config): AMQPChannel
    {
        $connection = $this->connection($name, $config);

        try {
            return $connection->channel();
        } catch (Throwable) {
            // A cached socket can look connected while the broker already closed
            // it. Recreate the connection once before giving up.
            $this->disconnect($name);

            return $this->connection($name, $config)->channel();
        }
    }

    public function disconnect(?string $name = null): void
    {
        $names = $name === null ? array_keys($this->connections) : [$name];

        foreach ($names as $connectionName) {
            $connection = $this->connections[$connectionName] ?? null;
            unset($this->connections[$connectionName]);

            if (! $connection instanceof AMQPStreamConnection) {
                continue;
            }

            try {
                $connection->close();
            } catch (Throwable) {
                // Closing a broken connection must not mask the original error.
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function create(array $config): AMQPStreamConnection
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 5672);
        $username = (string) ($config['username'] ?? 'guest');
        $password = (string) ($config['password'] ?? 'guest');

        return new AMQPStreamConnection(
            $host,
            $port,
            $username,
            $password,
            (string) ($config['vhost'] ?? '/'),
            (bool) ($config['insist'] ?? false),
            (string) ($config['login_method'] ?? 'AMQPLAIN'),
            $config['login_response'] ?? null,
            (string) ($config['locale'] ?? 'en_US'),
            (float) ($config['connection_timeout'] ?? 3.0),
            (float) ($config['read_write_timeout'] ?? 6.0),
            $config['context'] ?? null,
            (bool) ($config['keepalive'] ?? false),
            (int) ($config['heartbeat'] ?? 0),
            (float) ($config['channel_rpc_timeout'] ?? 0.0),
            $config['ssl_protocol'] ?? null
        );
    }
}
