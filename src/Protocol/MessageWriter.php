<?php

declare(strict_types=1);

namespace DbgpClient\Protocol;

use DbgpClient\Connection\ConnectionInterface;

final readonly class MessageWriter
{
    public function __construct(
        private ConnectionInterface $connection,
        private TransactionIdGenerator $idGenerator,
    ) {
    }

    /**
     * @param array<string, string|int|bool|null> $args
     */
    public function send(string $command, array $args = [], ?string $data = null): int
    {
        $transactionId = $this->idGenerator->next();

        $parts = [$command];

        foreach ($args as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $parts[] = "-{$key}";
            } else {
                $parts[] = "-{$key}";
                $parts[] = (string) $value;
            }
        }

        $parts[] = '-i';
        $parts[] = (string) $transactionId;

        $message = implode(' ', $parts);

        if ($data !== null) {
            $message .= ' -- ' . base64_encode($data);
        }

        $message .= "\0";

        $this->connection->write($message);

        return $transactionId;
    }
}
