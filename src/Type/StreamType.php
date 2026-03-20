<?php

declare(strict_types=1);

namespace DbgpClient\Type;

enum StreamType: string
{
    case Stdout = 'stdout';
    case Stderr = 'stderr';
}
