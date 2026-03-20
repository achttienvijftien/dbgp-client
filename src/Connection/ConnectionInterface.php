<?php

declare(strict_types=1);

namespace DbgpClient\Connection;

interface ConnectionInterface
{
    public function read(int $length): string;

    public function readUntil(string $delimiter): string;

    public function write(string $data): int;

    public function close(): void;

    public function isConnected(): bool;

    public function setReadTimeout(float $seconds): void;
}
