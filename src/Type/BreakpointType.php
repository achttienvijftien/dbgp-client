<?php

declare(strict_types=1);

namespace DbgpClient\Type;

enum BreakpointType: string
{
    case Line = 'line';
    case Call = 'call';
    case Return = 'return';
    case Exception = 'exception';
    case Conditional = 'conditional';
    case Watch = 'watch';
}
