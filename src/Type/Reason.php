<?php

declare(strict_types=1);

namespace DbgpClient\Type;

enum Reason: string
{
    case Ok = 'ok';
    case Error = 'error';
    case Aborted = 'aborted';
    case Exception = 'exception';
}
