<?php

declare(strict_types=1);

namespace DbgpClient\Tests\Unit\Protocol;

use DbgpClient\Connection\ConnectionInterface;
use DbgpClient\Exception\ProtocolException;
use DbgpClient\Protocol\MessageReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageReaderTest extends TestCase
{
    #[Test]
    public function readsParsesLengthPrefixedMessage(): void
    {
        $xml = '<?xml version="1.0"?><response command="status"/>';
        $message = strlen($xml) . "\0" . $xml . "\0";

        $connection = $this->createMockConnection($message);
        $reader = new MessageReader($connection);

        $result = $reader->read();

        self::assertSame($xml, $result);
    }

    #[Test]
    public function throwsOnInvalidLength(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('readUntil')
            ->with("\0")
            ->willReturn('invalid');

        $reader = new MessageReader($connection);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid message length');

        $reader->read();
    }

    #[Test]
    public function throwsOnMissingTerminator(): void
    {
        $xml = '<response/>';

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('readUntil')
            ->with("\0")
            ->willReturn((string) strlen($xml));
        $connection->method('read')
            ->willReturnOnConsecutiveCalls($xml, 'X');

        $reader = new MessageReader($connection);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Message not null-terminated');

        $reader->read();
    }

    private function createMockConnection(string $data): ConnectionInterface
    {
        $position = 0;

        $connection = $this->createMock(ConnectionInterface::class);

        $connection->method('readUntil')
            ->willReturnCallback(function (string $delimiter) use (&$position, $data): string {
                $result = '';
                while ($position < strlen($data)) {
                    $char = $data[$position++];
                    if ($char === $delimiter) {
                        break;
                    }
                    $result .= $char;
                }
                return $result;
            });

        $connection->method('read')
            ->willReturnCallback(function (int $length) use (&$position, $data): string {
                $result = substr($data, $position, $length);
                $position += $length;
                return $result;
            });

        return $connection;
    }
}
