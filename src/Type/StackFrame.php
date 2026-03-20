<?php

declare(strict_types=1);

namespace DbgpClient\Type;

final readonly class StackFrame
{
    public function __construct(
        public int $level,
        public string $type,
        public string $filename,
        public int $lineno,
        public ?string $where = null,
        public ?string $cmdbegin = null,
        public ?string $cmdend = null,
    ) {
    }

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        return new self(
            level: (int) $xml['level'],
            type: (string) $xml['type'],
            filename: (string) $xml['filename'],
            lineno: (int) $xml['lineno'],
            where: isset($xml['where']) ? (string) $xml['where'] : null,
            cmdbegin: isset($xml['cmdbegin']) ? (string) $xml['cmdbegin'] : null,
            cmdend: isset($xml['cmdend']) ? (string) $xml['cmdend'] : null,
        );
    }
}
