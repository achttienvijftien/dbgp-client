<?php

declare(strict_types=1);

namespace DbgpClient;

use DbgpClient\Connection\ConnectionInterface;
use DbgpClient\Protocol\MessageReader;
use DbgpClient\Protocol\MessageWriter;
use DbgpClient\Protocol\TransactionIdGenerator;
use DbgpClient\Response\InitResponse;
use DbgpClient\Response\NotifyResponse;
use DbgpClient\Response\Response;
use DbgpClient\Response\StreamResponse;
use DbgpClient\Type\BreakpointType;
use DbgpClient\Type\HitCondition;

final class Session
{
    public readonly InitResponse $init;

    private readonly MessageReader $reader;
    private readonly MessageWriter $writer;
    private readonly ConnectionInterface $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->reader = new MessageReader($connection);
        $this->writer = new MessageWriter($connection, new TransactionIdGenerator());

        $initXml = $this->reader->read();
        $this->init = InitResponse::fromXml($initXml);
    }

    /**
     * @param array<string, string|int|bool|null> $args
     */
    public function sendCommand(string $command, array $args = [], ?string $data = null): Response
    {
        $this->writer->send($command, $args, $data);
        $xml = $this->reader->read();

        return Response::fromXml($xml);
    }

    /**
     * Send an execution command (run, step_into, step_over, step_out) without
     * blocking to read the response.  The response will arrive asynchronously
     * when Xdebug hits a breakpoint or the script finishes; retrieve it by
     * calling readResponse() once the underlying socket is readable.
     *
     * @param array<string, string|int|bool|null> $args
     * @return int Transaction ID that will be echoed back in the response.
     */
    public function sendCommandAsync(string $command, array $args = [], ?string $data = null): int
    {
        return $this->writer->send($command, $args, $data);
    }

    public function readResponse(): Response|StreamResponse|NotifyResponse
    {
        $xml = $this->reader->read();

        if (StreamResponse::isStreamXml($xml)) {
            return StreamResponse::fromXml($xml);
        }

        if (NotifyResponse::isNotifyXml($xml)) {
            return NotifyResponse::fromXml($xml);
        }

        return Response::fromXml($xml);
    }

    public function status(): Response
    {
        return $this->sendCommand('status');
    }

    public function run(): Response
    {
        return $this->sendCommand('run');
    }

    public function stepInto(): Response
    {
        return $this->sendCommand('step_into');
    }

    public function stepOver(): Response
    {
        return $this->sendCommand('step_over');
    }

    public function stepOut(): Response
    {
        return $this->sendCommand('step_out');
    }

    public function stop(): Response
    {
        return $this->sendCommand('stop');
    }

    public function detach(): Response
    {
        return $this->sendCommand('detach');
    }

    public function featureGet(string $name): Response
    {
        return $this->sendCommand('feature_get', ['n' => $name]);
    }

    public function featureSet(string $name, string $value): Response
    {
        return $this->sendCommand('feature_set', ['n' => $name, 'v' => $value]);
    }

    public function breakpointSet(
        BreakpointType $type,
        ?string $file = null,
        ?int $line = null,
        ?string $function = null,
        ?string $class = null,
        ?string $exception = null,
        ?bool $state = null,
        ?bool $temporary = null,
        ?int $hitValue = null,
        ?HitCondition $hitCondition = null,
        ?string $expression = null,
    ): Response {
        $args = ['t' => $type->value];

        if ($file !== null) {
            $args['f'] = $file;
        }
        if ($line !== null) {
            $args['n'] = $line;
        }
        if ($function !== null) {
            $args['m'] = $function;
        }
        if ($class !== null) {
            $args['a'] = $class;
        }
        if ($exception !== null) {
            $args['x'] = $exception;
        }
        if ($state !== null) {
            $args['s'] = $state ? 'enabled' : 'disabled';
        }
        if ($temporary !== null) {
            $args['r'] = $temporary ? 1 : 0;
        }
        if ($hitValue !== null) {
            $args['h'] = $hitValue;
        }
        if ($hitCondition !== null) {
            $args['o'] = $hitCondition->value;
        }

        return $this->sendCommand('breakpoint_set', $args, $expression);
    }

    public function breakpointGet(int $id): Response
    {
        return $this->sendCommand('breakpoint_get', ['d' => $id]);
    }

    public function breakpointUpdate(
        int $id,
        ?bool $state = null,
        ?int $line = null,
        ?int $hitValue = null,
        ?HitCondition $hitCondition = null,
    ): Response {
        $args = ['d' => $id];

        if ($state !== null) {
            $args['s'] = $state ? 'enabled' : 'disabled';
        }
        if ($line !== null) {
            $args['n'] = $line;
        }
        if ($hitValue !== null) {
            $args['h'] = $hitValue;
        }
        if ($hitCondition !== null) {
            $args['o'] = $hitCondition->value;
        }

        return $this->sendCommand('breakpoint_update', $args);
    }

    public function breakpointRemove(int $id): Response
    {
        return $this->sendCommand('breakpoint_remove', ['d' => $id]);
    }

    public function breakpointList(): Response
    {
        return $this->sendCommand('breakpoint_list');
    }

    public function stackDepth(): Response
    {
        return $this->sendCommand('stack_depth');
    }

    public function stackGet(?int $depth = null): Response
    {
        $args = [];
        if ($depth !== null) {
            $args['d'] = $depth;
        }

        return $this->sendCommand('stack_get', $args);
    }

    public function contextNames(?int $depth = null): Response
    {
        $args = [];
        if ($depth !== null) {
            $args['d'] = $depth;
        }

        return $this->sendCommand('context_names', $args);
    }

    public function contextGet(?int $contextId = null, ?int $depth = null): Response
    {
        $args = [];
        if ($contextId !== null) {
            $args['c'] = $contextId;
        }
        if ($depth !== null) {
            $args['d'] = $depth;
        }

        return $this->sendCommand('context_get', $args);
    }

    public function propertyGet(
        string $name,
        ?int $depth = null,
        ?int $contextId = null,
        ?int $maxData = null,
        ?int $page = null,
    ): Response {
        $args = ['n' => $name];

        if ($depth !== null) {
            $args['d'] = $depth;
        }
        if ($contextId !== null) {
            $args['c'] = $contextId;
        }
        if ($maxData !== null) {
            $args['m'] = $maxData;
        }
        if ($page !== null) {
            $args['p'] = $page;
        }

        return $this->sendCommand('property_get', $args);
    }

    public function propertySet(string $name, string $value, ?string $type = null, ?int $depth = null): Response
    {
        $args = ['n' => $name];

        if ($type !== null) {
            $args['t'] = $type;
        }
        if ($depth !== null) {
            $args['d'] = $depth;
        }

        return $this->sendCommand('property_set', $args, $value);
    }

    public function eval(string $expression): Response
    {
        return $this->sendCommand('eval', [], $expression);
    }

    public function source(?string $fileUri = null, ?int $beginLine = null, ?int $endLine = null): Response
    {
        $args = [];

        if ($fileUri !== null) {
            $args['f'] = $fileUri;
        }
        if ($beginLine !== null) {
            $args['b'] = $beginLine;
        }
        if ($endLine !== null) {
            $args['e'] = $endLine;
        }

        return $this->sendCommand('source', $args);
    }

    public function typemapGet(): Response
    {
        return $this->sendCommand('typemap_get');
    }

    public function stdout(int $mode): Response
    {
        return $this->sendCommand('stdout', ['c' => $mode]);
    }

    public function stderr(int $mode): Response
    {
        return $this->sendCommand('stderr', ['c' => $mode]);
    }

    public function close(): void
    {
        $this->connection->close();
    }

    public function isConnected(): bool
    {
        return $this->connection->isConnected();
    }
}
