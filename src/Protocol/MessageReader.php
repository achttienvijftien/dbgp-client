<?php

declare(strict_types=1);

namespace DbgpClient\Protocol;

use DbgpClient\Connection\ConnectionInterface;
use DbgpClient\Exception\ProtocolException;

final readonly class MessageReader
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {
    }

    public function read(): string
    {
        $lengthStr = $this->connection->readUntil("\0");

        if (!ctype_digit($lengthStr)) {
            throw new ProtocolException("Invalid message length: {$lengthStr}");
        }

        $length = (int) $lengthStr;
        $xml = $this->connection->read($length);

        $terminator = $this->connection->read(1);
        if ($terminator !== "\0") {
            throw new ProtocolException('Message not null-terminated');
        }

        return $xml;
    }

    public function readWithTimeout(float $timeout): ?string
    {
        $this->connection->setReadTimeout($timeout);

        try {
            return $this->read();
        } catch (ProtocolException) {
            return null;
        }
    }
}
