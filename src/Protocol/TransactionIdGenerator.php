<?php

declare(strict_types=1);

namespace DbgpClient\Protocol;

final class TransactionIdGenerator
{
    private int $counter = 0;

    public function next(): int
    {
        return ++$this->counter;
    }

    public function current(): int
    {
        return $this->counter;
    }
}
