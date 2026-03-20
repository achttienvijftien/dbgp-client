<?php

declare(strict_types=1);

namespace DbgpClient\Type;

final readonly class Breakpoint
{
    public function __construct(
        public int $id,
        public BreakpointType $type,
        public BreakpointState $state,
        public ?string $filename = null,
        public ?int $lineno = null,
        public ?string $function = null,
        public ?string $class = null,
        public ?string $exception = null,
        public ?int $hitCount = null,
        public ?int $hitValue = null,
        public ?HitCondition $hitCondition = null,
        public ?string $expression = null,
        /** 'resolved' | 'unresolved' | null — populated from breakpoint_resolved notify */
        public ?string $resolved = null,
    ) {
    }

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        return new self(
            id: (int) $xml['id'],
            type: BreakpointType::from((string) $xml['type']),
            state: BreakpointState::from((string) $xml['state']),
            filename: isset($xml['filename']) ? (string) $xml['filename'] : null,
            lineno: isset($xml['lineno']) ? (int) $xml['lineno'] : null,
            function: isset($xml['function']) ? (string) $xml['function'] : null,
            class: isset($xml['class']) ? (string) $xml['class'] : null,
            exception: isset($xml['exception']) ? (string) $xml['exception'] : null,
            hitCount: isset($xml['hit_count']) ? (int) $xml['hit_count'] : null,
            hitValue: isset($xml['hit_value']) ? (int) $xml['hit_value'] : null,
            hitCondition: isset($xml['hit_condition']) ? HitCondition::from((string) $xml['hit_condition']) : null,
            expression: isset($xml->expression) ? (string) $xml->expression : null,
            resolved: isset($xml['resolved']) ? (string) $xml['resolved'] : null,
        );
    }
}
