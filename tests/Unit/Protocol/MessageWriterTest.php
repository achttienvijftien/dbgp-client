<?php

declare(strict_types=1);

namespace DbgpClient\Tests\Unit\Protocol;

use DbgpClient\Connection\ConnectionInterface;
use DbgpClient\Protocol\MessageWriter;
use DbgpClient\Protocol\TransactionIdGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageWriterTest extends TestCase
{
    #[Test]
    public function sendsSimpleCommand(): void
    {
        $written = '';
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('write')
            ->willReturnCallback(function (string $data) use (&$written): int {
                $written = $data;
                return strlen($data);
            });

        $writer = new MessageWriter($connection, new TransactionIdGenerator());

        $transactionId = $writer->send('status');

        self::assertSame(1, $transactionId);
        self::assertSame("status -i 1\0", $written);
    }

    #[Test]
    public function sendsCommandWithArguments(): void
    {
        $written = '';
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('write')
            ->willReturnCallback(function (string $data) use (&$written): int {
                $written = $data;
                return strlen($data);
            });

        $writer = new MessageWriter($connection, new TransactionIdGenerator());

        $writer->send('breakpoint_set', ['t' => 'line', 'f' => 'file.php', 'n' => 10]);

        self::assertSame("breakpoint_set -t line -f file.php -n 10 -i 1\0", $written);
    }

    #[Test]
    public function sendsCommandWithData(): void
    {
        $written = '';
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('write')
            ->willReturnCallback(function (string $data) use (&$written): int {
                $written = $data;
                return strlen($data);
            });

        $writer = new MessageWriter($connection, new TransactionIdGenerator());

        $writer->send('eval', [], '$foo + $bar');

        $encoded = base64_encode('$foo + $bar');
        self::assertSame("eval -i 1 -- {$encoded}\0", $written);
    }

    #[Test]
    public function skipsNullAndFalseArguments(): void
    {
        $written = '';
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('write')
            ->willReturnCallback(function (string $data) use (&$written): int {
                $written = $data;
                return strlen($data);
            });

        $writer = new MessageWriter($connection, new TransactionIdGenerator());

        $writer->send('test', ['a' => 'value', 'b' => null, 'c' => false, 'd' => 0]);

        self::assertSame("test -a value -d 0 -i 1\0", $written);
    }

    #[Test]
    public function incrementsTransactionId(): void
    {
        $messages = [];
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('write')
            ->willReturnCallback(function (string $data) use (&$messages): int {
                $messages[] = $data;
                return strlen($data);
            });

        $writer = new MessageWriter($connection, new TransactionIdGenerator());

        $id1 = $writer->send('status');
        $id2 = $writer->send('status');
        $id3 = $writer->send('status');

        self::assertSame(1, $id1);
        self::assertSame(2, $id2);
        self::assertSame(3, $id3);

        self::assertSame("status -i 1\0", $messages[0]);
        self::assertSame("status -i 2\0", $messages[1]);
        self::assertSame("status -i 3\0", $messages[2]);
    }
}
