<?php declare(strict_types=1);

/**
 * Issues commands to Redis server over TCP/UNIX socket and parses responses
 *
 * @package ziogas\Redis
 *
 * Based on https://github.com/ziogas/PHP-Redis-implementation which was
 * released under MIT License by Arminas Žukauskas - arminas@ini.lt
 */

namespace ziogas\Redis;

use \ziogas\Redis\Exception as RedisException;

class Socket
{
    const INT = ':';
    const STRING = '+';
    const BULK = '$';
    const MULTIBULK = '*';
    const ERROR = '-';
    const NL = "\r\n";

    /** @var resource|null */
    private mixed $socket = null;
    private string $socket_address = 'tcp://127.0.0.1:6379';
    private int $timeout = 30;
    private int $connect_timeout = 3;

    /** @var array<string> */
    private array $queue = [];

    public function setOptions(
        ?string $socket_address = null,
        ?int $timeout = null,
        ?int $connect_timeout = null
    ): bool {
        if (!empty($socket_address)) {
            $this->socket_address = $socket_address;
        }
        if (!empty($timeout)) {
            $this->timeout = $timeout;
        }
        if (!empty($connect_timeout)) {
            $this->connect_timeout = $connect_timeout;
        }

        return true;
    }

    public function connect(): bool
    {
        if ($this->isConnected()) {
            return true;
        }

        $socket = @stream_socket_client(
            $this->socket_address,
            $errno,
            $errstr,
            $this->connect_timeout
        );

        if (is_resource($socket)) {
            $this->socket = $socket;
            stream_set_timeout($this->socket, $this->timeout);
        } else {
            $err = 'Could not connect to Redis server (' . $this->socket_address . ')';
            if ($errstr) {
                $err .= ': ' . $errstr;
            }
            throw new RedisException($err, $errno);
        }

        return true;
    }

    public function isConnected(): bool
    {
        return isset($this->socket) && is_resource($this->socket);
    }

    public function reconnect(): bool
    {
        $this->disconnect();
        return $this->connect();
    }

    public function disconnect(): void
    {
        if (isset($this->socket) && is_resource($this->socket)) {
            fclose($this->socket);
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @return array<string>
     */
    public function getQueue(): array
    {
        return $this->queue;
    }

    /**
     * @param string|int ...$args
     */
    private function buildCommand(...$args): string
    {
        $rlen = count($args);
        $output = '*' . $rlen . self::NL;
        foreach ($args as $arg) {
            $arg = strval($arg);
            $output .= '$' . strlen($arg) . self::NL . $arg . self::NL;
        }
        return $output;
    }

    /**
     * @param string|int ...$args
     */
    public function mutli(...$args): bool
    {
        if (empty($args)) {
            throw new RedisException('Too few arguments passed');
        }

        $command = $this->buildCommand(...$args);
        $this->queue[] = $command;

        return true;
    }

    /**
     * @param array<string> $commands
     */
    private function execute(array $commands = []): ?bool
    {
        if (count($commands) < 1) {
            return null;
        }

        if (!$this->isConnected()) {
            $this->connect();
        }

        $command = implode(self::NL, $commands) . self::NL;
        if (isset($this->socket) && is_resource($this->socket)) {
            fwrite($this->socket, $command);
        }

        return true;
    }

    /**
     * @param string|int ...$args
     * @return string|int|array<mixed>|null|bool
     */
    public function run(...$args): string|int|array|null|bool
    {
        if (!$this->queue) {
            if (empty($args)) {
                throw new RedisException('Too few arguments passed');
            }
            // run single command
            $command = $this->buildCommand(...$args);
            $this->execute([$command]);
            $response = $this->getResponse();
        } else {
            if (!empty($args)) {
                $err = 'Cannot run a single command during transaction';
                throw new \RuntimeException($err);
            }
            // run multiple commands in transaction
            $multi = $this->buildCommand('MULTI');
            array_unshift($this->queue, $multi);
            $exec = $this->buildCommand('EXEC');
            array_unshift($this->queue, $exec);
            $num_commands = $this->execute($this->queue);
            $this->queue = [];
            $response = [];
            for ($i = 0; $i < $num_commands; $i++) {
                $response[] = $this->getResponse();
            }
        }

        return $response;
    }

    /**
     * @return string|int|array<mixed>|null|bool
     */
    private function getResponse(): string|int|array|null|bool
    {
        if (!isset($this->socket) || !is_resource($this->socket)) {
            return false;
        }
        $response = false;

        $char = fgetc($this->socket);
        if ($char === self::STRING) {
            $response = $this->getResponseString();
        } else if ($char === self::INT) {
            $response = $this->getResponseInt();
        } else if ($char === self::ERROR) {
            $this->throwResponseError();
        } else if ($char === self::BULK) {
            $response = $this->getResponseBulk();
        } else if ($char === self::MULTIBULK) {
            $response = $this->getResponseMultibulk();
        }

        return $response;
    }

    private function getResponseString(): string
    {
        if (!isset($this->socket) || !is_resource($this->socket)) {
            return '';
        }
        return trim(fgets($this->socket) ?: '');
    }

    private function getResponseInt(): int
    {
        return (int) $this->getResponseString();
    }

    private function throwResponseError(): void
    {
        $error = $this->getResponseString();
        throw new RedisException($error);
    }

    private function getResponseBulk(): ?string
    {
        if (!isset($this->socket) || !is_resource($this->socket)) {
            return null;
        }
        $response = $this->getResponseString();

        if ($response === '-1') {
            $response = null;
        } else {
            $bulk = null;
            $read = 0;
            if (strlen($response) > 1 && substr($response, 0, 1) === self::BULK) {
                $size = intval(substr($response, 1));
            } else {
                $size = intval($response);
            }
            while ($read < $size) {
                $diff = $size - $read;
                /** @var int<0, max> */
                $block_size = min($diff, 8192);
                $chunk = fread($this->socket, $block_size);
                if ($chunk !== false) {
                    $chunk_length = strlen($chunk);
                    $read += $chunk_length;
                    $bulk .= $chunk;
                } else {
                    fseek($this->socket, $read);
                }
            }
            fgets($this->socket);
            $response = $bulk;
        }

        return $response;
    }

    /**
     * @return array<string|int|array<mixed>|null|bool>|null
     */
    private function getResponseMultibulk(): ?array
    {
        $num_responses = $this->getResponseString();
        $response = false;

        if ($num_responses === '-1') {
            $response = null;
        } else {
            $response = [];
            for ($i = 0; $i < $num_responses; $i++) {
                $response[] = $this->getResponse();
            }
        }

        return $response;
    }
}
