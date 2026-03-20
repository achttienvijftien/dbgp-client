<?php

declare(strict_types=1);

namespace DbgpClient\Connection;

use DbgpClient\Exception\ConnectionException;

final class Connection implements ConnectionInterface
{
    /** @var resource|null */
    private $socket;

    /**
     * @param resource $socket
     */
    public function __construct($socket)
    {
        $this->socket = $socket;
    }

    public function read(int $length): string
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Connection is closed');
        }

        $data = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = @fread($this->socket, $remaining);

            if ($chunk === false || $chunk === '') {
                if (feof($this->socket)) {
                    throw new ConnectionException('Connection closed by peer');
                }
                throw new ConnectionException('Failed to read from socket');
            }

            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }

    public function readUntil(string $delimiter): string
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Connection is closed');
        }

        $data = '';

        while (true) {
            $byte = @fread($this->socket, 1);

            if ($byte === false || $byte === '') {
                if (feof($this->socket)) {
                    throw new ConnectionException('Connection closed by peer');
                }
                throw new ConnectionException('Failed to read from socket');
            }

            if ($byte === $delimiter) {
                break;
            }

            $data .= $byte;
        }

        return $data;
    }

    public function write(string $data): int
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Connection is closed');
        }

        $written = @fwrite($this->socket, $data);

        if ($written === false) {
            throw new ConnectionException('Failed to write to socket');
        }

        return $written;
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null && !feof($this->socket);
    }

    public function setReadTimeout(float $seconds): void
    {
        if ($this->socket === null) {
            return;
        }

        $sec = (int) $seconds;
        $usec = (int) (($seconds - $sec) * 1_000_000);
        stream_set_timeout($this->socket, $sec, $usec);
    }
}
