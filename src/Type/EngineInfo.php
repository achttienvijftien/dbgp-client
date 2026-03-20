<?php

declare(strict_types=1);

namespace DbgpClient\Type;

final readonly class EngineInfo
{
    public function __construct(
        public string $name,
        public ?string $version = null,
    ) {
    }
}
