<?php declare(strict_types=1);

/**
 * Abstract Redis commands with higher-level methods
 *
 * @package ziogas\Redis
 */

namespace ziogas\Redis;

use \ziogas\Redis\Exception as RedisException;

class Client
{
    public function __construct(
        private readonly \ziogas\Redis\Socket $socket
    ) {
    }

    /**
     * @param array<mixed> $fields_values
     * @return array<mixed>
     */
    private function processFieldsValues(array $fields_values): array
    {
        $data = [];
        $n = count($fields_values);
        for ($i = 0; $i < $n; $i += 2) {
            $field = $fields_values[$i];
            $value = $fields_values[$i + 1];
            $data[$field] = $value;
        }

        return $data;
    }

    public function connect(
        ?string $socket_address = null,
        ?string $password = null,
        ?int $timeout = null,
        ?int $connect_timeout = null
    ): bool {
        if ($this->socket->isConnected()) {
            return true;
        }

        if (empty($socket_address)) {
            throw new RedisException('Missing Redis socket address');
        }
        $this->socket->setOptions($socket_address, $timeout, $connect_timeout);
        try {
            $this->socket->connect();
        } catch (\Exception $e) {
            throw new RedisException('Could not connect to Redis socket');
        }

        if (!empty($password)) {
            $response = $this->socket->run('AUTH', $password);
            if ($response !== 'OK') {
                throw new RedisException('Incorrect password');
            }
        }

        return true;
    }

    public function set(
        string $key,
        mixed $_value,
        ?int $ttl = null
    ): bool {
        $value = $_value;
        $_value = ''; // hide input from logs

        $this->connect();

        $args = ['SET', $key, $value];
        if ($ttl !== null) {
            $args[] = 'EX';
            $args[] = $ttl;
        }
        $response = $this->socket->run(...$args);
        if ($response !== 'OK') {
            throw new RedisException('Key was not set');
        }
        return true;
    }

    public function exists(string $key): bool
    {
        $this->connect();

        $response = $this->socket->run('EXISTS', $key);
        return $response === 1;
    }

    /**
     * @param string ...$keys
     * @return string|array<mixed>|bool|int|null
     */
    public function get(...$keys): string|array|bool|int|null
    {
        $this->connect();

        if (count($keys) === 1) {
            $response = $this->socket->run('GET', $keys[0]);
        } else {
            $response = $this->socket->run('MGET', ...$keys);
        }
        return $response;
    }

    public function expire(string $key, int $ttl): bool
    {
        $this->connect();

        $response = $this->socket->run('EXPIRE', $key, $ttl);
        return $response === 1;
    }

    public function expireat(string $key, int $time): bool
    {
        $this->connect();

        $response = $this->socket->run('EXPIREAT', $key, $time);
        return $response === 1;
    }

    public function ttl(string $key): int
    {
        $this->connect();

        $response = $this->socket->run('TTL', $key);
        if ($response === -2) {
            throw new RedisException('Key does not exist');
        } else if ($response === -1) {
            throw new RedisException('Key will not expire');
        }
        return intval($response);
    }

    /**
     * @param string ...$keys
     */
    public function del(...$keys): int
    {
        $this->connect();

        $response = $this->socket->run('DEL', ...$keys);
        if ($response !== count($keys)) {
            throw new RedisException('One or more keys was not deleted');
        }
        return $response;
    }

    /**
     * @param string ...$keys
     */
    public function unlink(...$keys): int
    {
        $this->connect();

        $response = $this->socket->run('UNLINK', ...$keys);
        if ($response !== count($keys)) {
            throw new RedisException('One or more keys was not unlinked');
        }
        return $response;
    }

    /**
     * @return array<mixed>
     */
    public function scan(?string $pattern = null): array
    {
        $this->connect();

        $keys = [];
        $cursor = 0;
        do {
            $args = ['SCAN', $cursor];
            if ($pattern) {
                $args[] = 'MATCH';
                $args[] = $pattern;
            }
            $response = $this->socket->run(...$args);
            if (!is_array($response)) {
                break;
            }
            $cursor = intval($response[0]);
            $keys = array_merge($keys, $response[1]);
        } while ($cursor !== 0);
        $keys = array_unique($keys);
        return $keys;
    }

    /**
     * @param mixed ...$_fields_values
     */
    public function hset(string $key, ...$_fields_values): int
    {
        $fields_values = $_fields_values;
        $_fields_values = []; // hide input from logs

        $this->connect();

        if (count($fields_values) === 1 && is_array($fields_values[0])) {
            $a = [];
            foreach ($fields_values[0] as $field => $value) {
                $a[] = $field;
                $a[] = $value;
            }
            $fields_values = $a;
        }
        $response = $this->socket->run('HSET', $key, ...$fields_values);
        if ($response !== (count($fields_values) / 2)) {
            throw new RedisException('One or more fields was not set');
        }
        return intval($response);
    }

    public function hexists(string $key, string $field): bool
    {
        $this->connect();

        $response = $this->socket->run('HEXISTS', $key, $field);
        return $response === 1;
    }

    public function hget(string $key, string $field): ?string
    {
        $this->connect();

        $response = $this->socket->run('HGET', $key, $field);
        if (is_string($response) || $response === null) {
            return $response;
        } else {
            return null;
        }
    }

    /**
     * @return array<mixed>
     */
    public function hgetall(string $key): array
    {
        $this->connect();

        $fields_values = $this->socket->run('HGETALL', $key);
        if (is_array($fields_values)) {
            return $this->processFieldsValues($fields_values);
        } else {
            return [$fields_values];
        }
    }

    public function hlen(string $key): int
    {
        $this->connect();

        return intval($this->socket->run('HLEN', $key));
    }

    /**
     * @param mixed ...$fields
     */
    public function hdel(string $key, ...$fields): int
    {
        $this->connect();

        if (count($fields) === 1 && is_array($fields[0])) {
            $fields = $fields[0];
        }
        $response = $this->socket->run('HDEL', $key, ...$fields);
        if ($response !== count($fields)) {
            throw new RedisException('One or more fields was not deleted');
        }
        return $response;
    }

    /**
     * @return array<mixed>
     */
    public function hscan(string $key, ?string $pattern = null): array
    {
        $this->connect();

        $keys = [];
        $cursor = 0;
        do {
            $args = ['HSCAN', $key, $cursor];
            if ($pattern) {
                $args[] = 'MATCH';
                $args[] = $pattern;
            }
            $response = $this->socket->run(...$args);
            if (!is_array($response)) {
                break;
            }
            $cursor = intval($response[0]);
            $keys = array_merge($keys, $response[1]);
        } while ($cursor !== 0);
        $keys = array_unique($keys);
        return $keys;
    }

}
