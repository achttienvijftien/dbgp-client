<?php

declare(strict_types=1);

namespace DbgpClient\Tests\Unit;

use DbgpClient\Connection\ConnectionInterface;
use DbgpClient\Session;
use DbgpClient\Type\BreakpointType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    #[Test]
    public function parsesInitPacket(): void
    {
        $initXml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <init xmlns="urn:debugger_protocol_v1" appid="12345" idekey="PHPSTORM"
                  session="" thread="" parent="" language="PHP" protocol_version="1.0"
                  fileuri="file:///test.php">
                <engine version="3.1.6"><![CDATA[Xdebug]]></engine>
            </init>';

        $connection = $this->createMockConnection($initXml, []);
        $session = new Session($connection);

        self::assertSame('12345', $session->init->appId);
        self::assertSame('PHPSTORM', $session->init->ideKey);
        self::assertSame('PHP', $session->init->language);
        self::assertSame('file:///test.php', $session->init->fileUri);
        self::assertSame('Xdebug', $session->init->engine->name);
        self::assertSame('3.1.6', $session->init->engine->version);
    }

    #[Test]
    public function sendsStatusCommand(): void
    {
        $initXml = $this->getInitXml();
        $statusXml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="status"
                      transaction_id="1" status="starting" reason="ok"/>';

        $written = [];
        $connection = $this->createMockConnection($initXml, [$statusXml], $written);
        $session = new Session($connection);

        $response = $session->status();

        self::assertSame("status -i 1\0", $written[0]);
        self::assertSame('status', $response->command);
    }

    #[Test]
    public function sendsRunCommand(): void
    {
        $initXml = $this->getInitXml();
        $runXml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="run"
                      transaction_id="1" status="stopped" reason="ok"/>';

        $written = [];
        $connection = $this->createMockConnection($initXml, [$runXml], $written);
        $session = new Session($connection);

        $response = $session->run();

        self::assertSame("run -i 1\0", $written[0]);
        self::assertSame('run', $response->command);
    }

    #[Test]
    public function sendsBreakpointSetCommand(): void
    {
        $initXml = $this->getInitXml();
        $bpXml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="breakpoint_set"
                      transaction_id="1" id="1"/>';

        $written = [];
        $connection = $this->createMockConnection($initXml, [$bpXml], $written);
        $session = new Session($connection);

        $response = $session->breakpointSet(
            BreakpointType::Line,
            'file:///test.php',
            10
        );

        self::assertStringContainsString('breakpoint_set', $written[0]);
        self::assertStringContainsString('-t line', $written[0]);
        self::assertStringContainsString('-f file:///test.php', $written[0]);
        self::assertStringContainsString('-n 10', $written[0]);
        self::assertSame(1, $response->id);
    }

    #[Test]
    public function sendsEvalCommand(): void
    {
        $initXml = $this->getInitXml();
        $evalXml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="eval" transaction_id="1">
                <property type="int">42</property>
            </response>';

        $written = [];
        $connection = $this->createMockConnection($initXml, [$evalXml], $written);
        $session = new Session($connection);

        $response = $session->eval('1 + 1');

        self::assertStringContainsString('eval', $written[0]);
        self::assertStringContainsString('-- ' . base64_encode('1 + 1'), $written[0]);
        self::assertCount(1, $response->properties);
    }

    #[Test]
    public function sendsStepCommands(): void
    {
        $initXml = $this->getInitXml();
        $stepXml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="step_into"
                      transaction_id="1" status="break" reason="ok"/>';

        $written = [];
        $connection = $this->createMockConnection($initXml, [$stepXml, $stepXml, $stepXml], $written);
        $session = new Session($connection);

        $session->stepInto();
        $session->stepOver();
        $session->stepOut();

        self::assertStringContainsString('step_into', $written[0]);
        self::assertStringContainsString('step_over', $written[1]);
        self::assertStringContainsString('step_out', $written[2]);
    }

    #[Test]
    public function sendCommandAsyncWritesWithoutReading(): void
    {
        $initXml = $this->getInitXml();

        $written = [];
        // No response queued — sendCommandAsync must NOT try to read a response
        $connection = $this->createMockConnection($initXml, [], $written);
        $session = new Session($connection);

        $txId = $session->sendCommandAsync('run');

        self::assertCount(1, $written);
        self::assertSame("run -i 1\0", $written[0]);
        self::assertSame(1, $txId);
    }

    #[Test]
    public function sendCommandAsyncSharesTxIdSequenceWithSendCommand(): void
    {
        $initXml = $this->getInitXml();
        $statusXml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="status"
                      transaction_id="2" status="starting" reason="ok"/>';

        $written = [];
        $connection = $this->createMockConnection($initXml, [$statusXml], $written);
        $session = new Session($connection);

        // First command: async (tx 1)
        $asyncTxId = $session->sendCommandAsync('run');
        // Second command: sync (tx 2)
        $response = $session->status();

        self::assertSame(1, $asyncTxId);
        self::assertStringContainsString('-i 1', $written[0]);
        self::assertStringContainsString('-i 2', $written[1]);
        self::assertSame('status', $response->command);
    }

    #[Test]
    public function sendsContextCommands(): void
    {
        $initXml = $this->getInitXml();
        $contextNamesXml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="context_names" transaction_id="1">
                <context name="Locals" id="0"/>
            </response>';
        $contextGetXml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="context_get" transaction_id="2">
                <property name="$foo" fullname="$foo" type="int">42</property>
            </response>';

        $written = [];
        $connection = $this->createMockConnection($initXml, [$contextNamesXml, $contextGetXml], $written);
        $session = new Session($connection);

        $names = $session->contextNames();
        $context = $session->contextGet(0);

        self::assertCount(1, $names->contexts);
        self::assertCount(1, $context->properties);
    }

    private function getInitXml(): string
    {
        return '<?xml version="1.0" encoding="iso-8859-1"?>
            <init xmlns="urn:debugger_protocol_v1" appid="1" idekey="test"
                  session="" thread="" parent="" language="PHP" protocol_version="1.0"
                  fileuri="file:///test.php">
                <engine version="1.0"><![CDATA[Test]]></engine>
            </init>';
    }

    /**
     * @param list<string> $responses
     * @param list<string> $written
     */
    private function createMockConnection(string $initXml, array $responses, array &$written = []): ConnectionInterface
    {
        $allResponses = array_merge([$initXml], $responses);
        $responseIndex = 0;
        $position = 0;
        $currentData = '';

        $prepareNextResponse = function () use (&$allResponses, &$responseIndex, &$currentData, &$position): void {
            if ($responseIndex < count($allResponses)) {
                $xml = $allResponses[$responseIndex++];
                $currentData = strlen($xml) . "\0" . $xml . "\0";
                $position = 0;
            }
        };

        $prepareNextResponse();

        $connection = $this->createMock(ConnectionInterface::class);

        $connection->method('readUntil')
            ->willReturnCallback(function (string $delimiter) use (&$position, &$currentData): string {
                $result = '';
                while ($position < strlen($currentData)) {
                    $char = $currentData[$position++];
                    if ($char === $delimiter) {
                        break;
                    }
                    $result .= $char;
                }
                return $result;
            });

        $connection->method('read')
            ->willReturnCallback(function (int $length) use (&$position, &$currentData, $prepareNextResponse): string {
                $result = substr($currentData, $position, $length);
                $position += $length;

                if ($position >= strlen($currentData)) {
                    $prepareNextResponse();
                }

                return $result;
            });

        $connection->method('write')
            ->willReturnCallback(function (string $data) use (&$written, $prepareNextResponse): int {
                $written[] = $data;
                return strlen($data);
            });

        $connection->method('isConnected')->willReturn(true);

        return $connection;
    }
}
