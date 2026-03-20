<?php

declare(strict_types=1);

namespace DbgpClient\Tests\Unit\Response;

use DbgpClient\Exception\ProtocolException;
use DbgpClient\Response\NotifyResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NotifyResponseTest extends TestCase
{
    #[Test]
    public function parsesBreakpointResolvedNotify(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <notify xmlns="urn:debugger_protocol_v1" name="breakpoint_resolved">
                <breakpoint id="42" type="line" state="enabled"
                            filename="file:///var/www/index.php" lineno="10" resolved="resolved"/>
            </notify>';

        $notify = NotifyResponse::fromXml($xml);

        self::assertSame('breakpoint_resolved', $notify->name);
        self::assertNotNull($notify->breakpoint);
        self::assertSame(42, $notify->breakpoint->id);
        self::assertSame(10, $notify->breakpoint->lineno);
        self::assertSame('file:///var/www/index.php', $notify->breakpoint->filename);
        self::assertSame('resolved', $notify->breakpoint->resolved);
    }

    #[Test]
    public function parsesBreakpointResolvedNotifyWithUnresolved(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <notify xmlns="urn:debugger_protocol_v1" name="breakpoint_resolved">
                <breakpoint id="7" type="line" state="disabled"
                            filename="file:///var/www/lib.php" lineno="25" resolved="unresolved"/>
            </notify>';

        $notify = NotifyResponse::fromXml($xml);

        self::assertSame('breakpoint_resolved', $notify->name);
        self::assertNotNull($notify->breakpoint);
        self::assertSame(7, $notify->breakpoint->id);
        self::assertSame('unresolved', $notify->breakpoint->resolved);
    }

    #[Test]
    public function parsesNotifyWithoutBreakpoint(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <notify xmlns="urn:debugger_protocol_v1" name="output">
            </notify>';

        $notify = NotifyResponse::fromXml($xml);

        self::assertSame('output', $notify->name);
        self::assertNull($notify->breakpoint);
    }

    #[Test]
    public function detectsNotifyXml(): void
    {
        self::assertTrue(NotifyResponse::isNotifyXml('<notify name="breakpoint_resolved">'));
        self::assertFalse(NotifyResponse::isNotifyXml('<response command="run">'));
        self::assertFalse(NotifyResponse::isNotifyXml('<init appid="1">'));
    }

    #[Test]
    public function throwsOnInvalidXml(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageMatches('/Failed to parse notify XML/');

        NotifyResponse::fromXml('not xml');
    }

    #[Test]
    public function throwsOnWrongRootElement(): void
    {
        $xml = '<?xml version="1.0"?><response xmlns="urn:debugger_protocol_v1" command="run"/>';

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageMatches('/Expected notify packet/');

        NotifyResponse::fromXml($xml);
    }
}
