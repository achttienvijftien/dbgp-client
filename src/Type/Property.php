<?php

declare(strict_types=1);

namespace DbgpClient\Type;

final readonly class Property
{
    /**
     * @param list<Property> $children
     */
    public function __construct(
        public string $name,
        public string $fullname,
        public string $type,
        public ?string $classname = null,
        public ?string $value = null,
        public ?string $encoding = null,
        public ?int $size = null,
        public ?int $numchildren = null,
        public ?int $page = null,
        public ?int $pagesize = null,
        public ?string $facet = null,
        public array $children = [],
    ) {
    }

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        $children = [];
        foreach ($xml->property as $child) {
            $children[] = self::fromXml($child);
        }

        $value = null;
        $content = trim((string) $xml);
        if ($content !== '') {
            $encoding = isset($xml['encoding']) ? (string) $xml['encoding'] : null;
            $value = $encoding === 'base64' ? base64_decode($content, true) : $content;
            if ($value === false) {
                $value = $content;
            }
        }

        return new self(
            name: (string) $xml['name'],
            fullname: (string) $xml['fullname'],
            type: (string) $xml['type'],
            classname: isset($xml['classname']) ? (string) $xml['classname'] : null,
            value: $value,
            encoding: isset($xml['encoding']) ? (string) $xml['encoding'] : null,
            size: isset($xml['size']) ? (int) $xml['size'] : null,
            numchildren: isset($xml['numchildren']) ? (int) $xml['numchildren'] : null,
            page: isset($xml['page']) ? (int) $xml['page'] : null,
            pagesize: isset($xml['pagesize']) ? (int) $xml['pagesize'] : null,
            facet: isset($xml['facet']) ? (string) $xml['facet'] : null,
            children: $children,
        );
    }
}
