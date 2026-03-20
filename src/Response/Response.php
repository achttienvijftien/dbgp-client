<?php

declare(strict_types=1);

namespace DbgpClient\Response;

use DbgpClient\Exception\ProtocolException;
use DbgpClient\Type\Breakpoint;
use DbgpClient\Type\Context;
use DbgpClient\Type\ErrorInfo;
use DbgpClient\Type\Property;
use DbgpClient\Type\Reason;
use DbgpClient\Type\StackFrame;
use DbgpClient\Type\Status;
use DbgpClient\Type\TypeMap;

final readonly class Response
{
    /**
     * @param list<StackFrame> $stack
     * @param list<Context> $contexts
     * @param list<Breakpoint> $breakpoints
     * @param list<Property> $properties
     * @param list<TypeMap> $typemap
     */
    public function __construct(
        public string $command,
        public int $transactionId,
        public ?Status $status = null,
        public ?Reason $reason = null,
        public ?int $success = null,
        public ?string $feature = null,
        public ?int $supported = null,
        public ?ErrorInfo $error = null,
        public array $stack = [],
        public array $contexts = [],
        public array $breakpoints = [],
        public array $properties = [],
        public array $typemap = [],
        public ?string $value = null,
        public ?string $encoding = null,
        public ?int $depth = null,
        public ?int $id = null,
        /** Exception class name from xdebug:message (Xdebug 3.x exception breaks). */
        public ?string $exceptionName = null,
        /** Exception message from xdebug:message (Xdebug 3.x exception breaks). */
        public ?string $exceptionMessage = null,
    ) {
    }

    public function isSuccess(): bool
    {
        if ($this->error !== null) {
            return false;
        }

        if ($this->success !== null) {
            return $this->success === 1;
        }

        return true;
    }

    public function expectMoreResponses(): bool
    {
        return $this->status === Status::Break;
    }

    public function shouldCloseConnection(): bool
    {
        return $this->command === 'stop' || $this->command === 'detach';
    }

    public function getDecodedValue(): ?string
    {
        if ($this->value === null) {
            return null;
        }

        if ($this->encoding === 'base64') {
            $decoded = base64_decode($this->value, true);
            return $decoded !== false ? $decoded : $this->value;
        }

        return $this->value;
    }

    public static function fromXml(string $xml): self
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $message = !empty($errors) ? $errors[0]->message : 'Unknown XML error';
            throw new ProtocolException("Failed to parse XML: {$message}");
        }

        $command = (string) $doc['command'];
        $transactionId = (int) $doc['transaction_id'];

        $status = isset($doc['status']) ? Status::tryFrom((string) $doc['status']) : null;
        $reason = isset($doc['reason']) ? Reason::tryFrom((string) $doc['reason']) : null;
        $success = isset($doc['success']) ? (int) $doc['success'] : null;
        $feature = isset($doc['feature_name']) ? (string) $doc['feature_name'] : null;
        $supported = isset($doc['supported']) ? (int) $doc['supported'] : null;
        $depth = isset($doc['depth']) ? (int) $doc['depth'] : null;
        $id = isset($doc['id']) ? (int) $doc['id'] : null;

        $error = null;
        if (isset($doc->error)) {
            $error = ErrorInfo::fromXml($doc->error);
        }

        $stack = [];
        foreach ($doc->stack as $stackXml) {
            $stack[] = StackFrame::fromXml($stackXml);
        }

        $contexts = [];
        foreach ($doc->context as $contextXml) {
            $contexts[] = Context::fromXml($contextXml);
        }

        $breakpoints = [];
        foreach ($doc->breakpoint as $bpXml) {
            $breakpoints[] = Breakpoint::fromXml($bpXml);
        }

        $properties = [];
        foreach ($doc->property as $propXml) {
            $properties[] = Property::fromXml($propXml);
        }

        $typemap = [];
        foreach ($doc->map as $mapXml) {
            $typemap[] = TypeMap::fromXml($mapXml);
        }

        // Parse xdebug:message for exception break info (Xdebug 3.x sends
        // reason="ok" but includes <xdebug:message exception="..."> child).
        $exceptionName = null;
        $exceptionMessage = null;
        $xdebugNs = $doc->getNamespaces(true)['xdebug'] ?? null;
        if ($xdebugNs !== null) {
            $xdebugChildren = $doc->children($xdebugNs);
            if (isset($xdebugChildren->message)) {
                $msgEl = $xdebugChildren->message;
                $attrs = $msgEl->attributes();
                $exName = (string) ($attrs['exception'] ?? '');
                if ($exName !== '') {
                    $exceptionName = $exName;
                    $exceptionMessage = trim((string) $msgEl);
                }
            }
        }

        $value = null;
        $encoding = isset($doc['encoding']) ? (string) $doc['encoding'] : null;
        $content = trim((string) $doc);
        if ($content !== '') {
            $value = $content;
        }

        return new self(
            command: $command,
            transactionId: $transactionId,
            status: $status,
            reason: $reason,
            success: $success,
            feature: $feature,
            supported: $supported,
            error: $error,
            stack: $stack,
            contexts: $contexts,
            breakpoints: $breakpoints,
            properties: $properties,
            typemap: $typemap,
            value: $value,
            encoding: $encoding,
            depth: $depth,
            id: $id,
            exceptionName: $exceptionName,
            exceptionMessage: $exceptionMessage,
        );
    }
}
