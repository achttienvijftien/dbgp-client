<?php

declare(strict_types=1);

namespace DbgpClient\Type;

final readonly class TypeMap
{
    public function __construct(
        public string $type,
        public string $name,
        public ?string $xsiType = null,
    ) {
    }

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        return new self(
            type: (string) $xml['type'],
            name: (string) $xml['name'],
            xsiType: isset($xml['xsi:type']) ? (string) $xml['xsi:type'] : null,
        );
    }
}
