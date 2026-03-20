<?php

declare(strict_types=1);

namespace DbgpClient;

use DbgpClient\Connection\Connection;
use DbgpClient\Exception\ConnectionException;

final class Server
{
    /** @var resource|null */
    private $socket;

    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 9003,
    ) {
    }

    public function listen(): void
    {
        $address = "tcp://{$this->host}:{$this->port}";
        $errno = 0;
        $errstr = '';

        $this->socket = @stream_socket_server($address, $errno, $errstr);

        if ($this->socket === false) {
            throw new ConnectionException("Failed to create server on {$address}: [{$errno}] {$errstr}");
        }
    }

    /**
     * Return the raw server socket resource so callers can add it to a
     * stream_select() watched-reads array.  Returns null before listen() is called.
     *
     * @return resource|null
     */
    public function getServerSocket()
    {
        return $this->socket;
    }

    /**
     * Accept an incoming connection and return the raw PHP stream resource.
     * Returns null when no client is waiting (non-blocking).
     * The caller is responsible for wrapping the resource in a ConnectionInterface
     * implementation and constructing a Session from it.
     *
     * @return resource|null
     */
    public function acceptRaw(float $timeout = 0.0): mixed
    {
        if ($this->socket === null) {
            throw new ConnectionException('Server is not listening');
        }

        // Pass 0 (not null) for zero timeout — null would use PHP's
        // default_socket_timeout (60s), blocking the caller.
        $timeoutArg = $timeout > 0 ? $timeout : 0;

        $client = @stream_socket_accept($this->socket, $timeoutArg);

        return $client !== false ? $client : null;
    }

    public function accept(float $timeout = 0.0): ?Session
    {
        if ($this->socket === null) {
            throw new ConnectionException('Server is not listening');
        }

        $timeoutArg = $timeout > 0 ? $timeout : null;

        $client = @stream_socket_accept($this->socket, $timeoutArg);

        if ($client === false) {
            return null;
        }

        $connection = new Connection($client);

        return new Session($connection);
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
