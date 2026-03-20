<?php

declare(strict_types=1);

namespace DbgpClient\Tests\Unit\Response;

use DbgpClient\Exception\ProtocolException;
use DbgpClient\Response\Response;
use DbgpClient\Type\Reason;
use DbgpClient\Type\Status;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    #[Test]
    public function parsesStatusResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="status"
                      transaction_id="1" status="starting" reason="ok"/>';

        $response = Response::fromXml($xml);

        self::assertSame('status', $response->command);
        self::assertSame(1, $response->transactionId);
        self::assertSame(Status::Starting, $response->status);
        self::assertSame(Reason::Ok, $response->reason);
        self::assertTrue($response->isSuccess());
    }

    #[Test]
    public function parsesBreakStatus(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="step_into"
                      transaction_id="2" status="break" reason="ok"/>';

        $response = Response::fromXml($xml);

        self::assertSame(Status::Break, $response->status);
        self::assertTrue($response->expectMoreResponses());
    }

    #[Test]
    public function parsesFeatureGetResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="feature_get"
                      transaction_id="1" feature_name="max_depth" supported="1">2</response>';

        $response = Response::fromXml($xml);

        self::assertSame('feature_get', $response->command);
        self::assertSame('max_depth', $response->feature);
        self::assertSame(1, $response->supported);
        self::assertSame('2', $response->value);
    }

    #[Test]
    public function parsesStackGetResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="stack_get" transaction_id="1">
                <stack level="0" type="file" filename="file:///test.php" lineno="10" where="{main}"/>
                <stack level="1" type="file" filename="file:///other.php" lineno="20" where="foo"/>
            </response>';

        $response = Response::fromXml($xml);

        self::assertCount(2, $response->stack);
        self::assertSame(0, $response->stack[0]->level);
        self::assertSame('file:///test.php', $response->stack[0]->filename);
        self::assertSame(10, $response->stack[0]->lineno);
        self::assertSame('{main}', $response->stack[0]->where);
    }

    #[Test]
    public function parsesContextNamesResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="context_names" transaction_id="1">
                <context name="Locals" id="0"/>
                <context name="Superglobals" id="1"/>
            </response>';

        $response = Response::fromXml($xml);

        self::assertCount(2, $response->contexts);
        self::assertSame('Locals', $response->contexts[0]->name);
        self::assertSame(0, $response->contexts[0]->id);
    }

    #[Test]
    public function parsesBreakpointListResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="breakpoint_list" transaction_id="1">
                <breakpoint id="1" type="line" state="enabled" filename="file:///test.php" lineno="10"/>
            </response>';

        $response = Response::fromXml($xml);

        self::assertCount(1, $response->breakpoints);
        self::assertSame(1, $response->breakpoints[0]->id);
        self::assertSame('file:///test.php', $response->breakpoints[0]->filename);
    }

    #[Test]
    public function parsesContextGetWithProperties(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="context_get" transaction_id="1">
                <property name="$foo" fullname="$foo" type="string" encoding="base64">YmFy</property>
                <property name="$num" fullname="$num" type="int">42</property>
            </response>';

        $response = Response::fromXml($xml);

        self::assertCount(2, $response->properties);
        self::assertSame('$foo', $response->properties[0]->name);
        self::assertSame('bar', $response->properties[0]->value);
        self::assertSame('$num', $response->properties[1]->name);
        self::assertSame('42', $response->properties[1]->value);
    }

    #[Test]
    public function parsesErrorResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="breakpoint_get" transaction_id="1">
                <error code="205"><message>No such breakpoint</message></error>
            </response>';

        $response = Response::fromXml($xml);

        self::assertNotNull($response->error);
        self::assertSame(205, $response->error->code);
        self::assertSame('No such breakpoint', $response->error->message);
        self::assertFalse($response->isSuccess());
    }

    #[Test]
    public function parsesTypemapResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="typemap_get" transaction_id="1">
                <map type="bool" name="bool"/>
                <map type="int" name="int"/>
                <map type="string" name="string"/>
            </response>';

        $response = Response::fromXml($xml);

        self::assertCount(3, $response->typemap);
        self::assertSame('bool', $response->typemap[0]->type);
        self::assertSame('int', $response->typemap[1]->type);
    }

    #[Test]
    public function parsesEvalResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="eval" transaction_id="1">
                <property type="int">42</property>
            </response>';

        $response = Response::fromXml($xml);

        self::assertCount(1, $response->properties);
        self::assertSame('int', $response->properties[0]->type);
        self::assertSame('42', $response->properties[0]->value);
    }

    #[Test]
    public function throwsOnInvalidXml(): void
    {
        $this->expectException(ProtocolException::class);

        Response::fromXml('not valid xml');
    }

    #[Test]
    public function detectsStopCommand(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="stop"
                      transaction_id="1" status="stopped" reason="ok"/>';

        $response = Response::fromXml($xml);

        self::assertTrue($response->shouldCloseConnection());
    }

    #[Test]
    public function detectsDetachCommand(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="detach"
                      transaction_id="1" status="stopping" reason="ok"/>';

        $response = Response::fromXml($xml);

        self::assertTrue($response->shouldCloseConnection());
    }

    #[Test]
    public function parsesSourceResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="source"
                      transaction_id="1" encoding="base64">PD9waHAKZWNobyAiaGkiOwo=</response>';

        $response = Response::fromXml($xml);

        self::assertSame('base64', $response->encoding);
        self::assertSame('PD9waHAKZWNobyAiaGkiOwo=', $response->value);
        self::assertSame("<?php\necho \"hi\";\n", $response->getDecodedValue());
    }

    #[Test]
    public function parsesStackDepthResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="stack_depth"
                      transaction_id="1" depth="3"/>';

        $response = Response::fromXml($xml);

        self::assertSame(3, $response->depth);
    }

    #[Test]
    public function parsesBreakpointSetResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>
            <response xmlns="urn:debugger_protocol_v1" command="breakpoint_set"
                      transaction_id="1" id="1"/>';

        $response = Response::fromXml($xml);

        self::assertSame(1, $response->id);
    }
}
