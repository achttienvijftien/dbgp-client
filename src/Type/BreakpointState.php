<?php

declare(strict_types=1);

namespace DbgpClient\Type;

enum BreakpointState: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
