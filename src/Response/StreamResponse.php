<?php

declare(strict_types=1);

namespace DbgpClient\Response;

use DbgpClient\Exception\ProtocolException;
use DbgpClient\Type\StreamType;

final readonly class StreamResponse
{
    public function __construct(
        public StreamType $type,
        public string $data,
        public string $encoding,
    ) {
    }

    public function getDecodedData(): string
    {
        if ($this->encoding === 'base64') {
            $decoded = base64_decode($this->data, true);
            return $decoded !== false ? $decoded : $this->data;
        }

        return $this->data;
    }

    public static function fromXml(string $xml): self
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $message = !empty($errors) ? $errors[0]->message : 'Unknown XML error';
            throw new ProtocolException("Failed to parse stream XML: {$message}");
        }

        if ($doc->getName() !== 'stream') {
            throw new ProtocolException("Expected stream packet, got: {$doc->getName()}");
        }

        return new self(
            type: StreamType::from((string) $doc['type']),
            data: (string) $doc,
            encoding: (string) ($doc['encoding'] ?? 'base64'),
        );
    }

    public static function isStreamXml(string $xml): bool
    {
        return str_contains($xml, '<stream ');
    }
}
