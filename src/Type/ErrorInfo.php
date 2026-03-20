<?php

declare(strict_types=1);

namespace DbgpClient\Type;

final readonly class ErrorInfo
{
    public function __construct(
        public int $code,
        public string $message,
    ) {
    }

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        return new self(
            code: (int) $xml['code'],
            message: (string) $xml->message,
        );
    }
}
