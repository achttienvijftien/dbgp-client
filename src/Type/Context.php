<?php

declare(strict_types=1);

namespace DbgpClient\Type;

final readonly class Context
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        return new self(
            id: (int) $xml['id'],
            name: (string) $xml['name'],
        );
    }
}
