<?php

declare(strict_types=1);

namespace DbgpClient\Tests\Unit\Response;

use DbgpClient\Exception\ProtocolException;
use DbgpClient\Response\InitResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InitResponseTest extends TestCase
{
    #[Test]
    public function parsesBasicInitPacket(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <init xmlns="urn:debugger_protocol_v1" appid="12345" idekey="PHPSTORM"
                  session="sess1" thread="1" parent="" language="PHP" protocol_version="1.0"
                  fileuri="file:///var/www/index.php">
                <engine version="3.4.0"><![CDATA[Xdebug]]></engine>
            </init>';

        $response = InitResponse::fromXml($xml);

        self::assertSame('12345', $response->appId);
        self::assertSame('PHPSTORM', $response->ideKey);
        self::assertSame('sess1', $response->session);
        self::assertSame('1', $response->thread);
        self::assertSame('PHP', $response->language);
        self::assertSame('1.0', $response->protocolVersion);
        self::assertSame('file:///var/www/index.php', $response->fileUri);
        self::assertSame('Xdebug', $response->engine->name);
        self::assertSame('3.4.0', $response->engine->version);
        self::assertNull($response->ctrlSocket);
    }

    #[Test]
    public function ctrlSocketIsNullWhenAttributeAbsent(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <init xmlns="urn:debugger_protocol_v1" appid="1" idekey="test"
                  session="" thread="" parent="" language="PHP" protocol_version="1.0"
                  fileuri="file:///test.php">
                <engine version="3.4.0"><![CDATA[Xdebug]]></engine>
            </init>';

        $response = InitResponse::fromXml($xml);

        self::assertNull($response->ctrlSocket);
    }

    #[Test]
    public function ctrlSocketParsedFromXdebugNamespace(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <init xmlns="urn:debugger_protocol_v1"
                  xmlns:xdebug="https://xdebug.org/dbgp/xdebug"
                  appid="1" idekey="test"
                  session="" thread="" parent="" language="PHP" protocol_version="1.0"
                  fileuri="file:///test.php"
                  xdebug:ctrl-socket="unix:///tmp/xdebug.ctrl.12345">
                <engine version="3.5.0"><![CDATA[Xdebug]]></engine>
            </init>';

        $response = InitResponse::fromXml($xml);

        self::assertSame('unix:///tmp/xdebug.ctrl.12345', $response->ctrlSocket);
    }

    #[Test]
    public function ctrlSocketWindowsNamedPipe(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <init xmlns="urn:debugger_protocol_v1"
                  xmlns:xdebug="https://xdebug.org/dbgp/xdebug"
                  appid="1" idekey="test"
                  session="" thread="" parent="" language="PHP" protocol_version="1.0"
                  fileuri="file:///test.php"
                  xdebug:ctrl-socket="\\\\.\\pipe\\xdebug-ctrl.42">
                <engine version="3.5.0"><![CDATA[Xdebug]]></engine>
            </init>';

        $response = InitResponse::fromXml($xml);

        self::assertSame('\\\\.\\pipe\\xdebug-ctrl.42', $response->ctrlSocket);
    }

    #[Test]
    public function throwsOnInvalidXml(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageMatches('/Failed to parse init XML/');

        InitResponse::fromXml('not xml at all');
    }

    #[Test]
    public function throwsOnWrongRootElement(): void
    {
        $xml = '<?xml version="1.0"?><response xmlns="urn:debugger_protocol_v1" command="status"/>';

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageMatches('/Expected init packet/');

        InitResponse::fromXml($xml);
    }
}
