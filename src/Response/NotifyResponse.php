<?php

declare(strict_types=1);

namespace DbgpClient\Response;

use DbgpClient\Exception\ProtocolException;
use DbgpClient\Type\Breakpoint;

final readonly class NotifyResponse
{
    public function __construct(
        public string $name,
        public ?Breakpoint $breakpoint = null,
    ) {
    }

    public static function fromXml(string $xml): self
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $message = !empty($errors) ? $errors[0]->message : 'Unknown XML error';
            throw new ProtocolException("Failed to parse notify XML: {$message}");
        }

        if ($doc->getName() !== 'notify') {
            throw new ProtocolException("Expected notify packet, got: {$doc->getName()}");
        }

        $breakpoint = null;
        if (isset($doc->breakpoint)) {
            $breakpoint = Breakpoint::fromXml($doc->breakpoint);
        }

        return new self(
            name: (string) $doc['name'],
            breakpoint: $breakpoint,
        );
    }

    public static function isNotifyXml(string $xml): bool
    {
        return str_contains($xml, '<notify ');
    }
}
