<?php

declare(strict_types=1);

namespace DbgpClient\Type;

enum Status: string
{
    case Starting = 'starting';
    case Stopping = 'stopping';
    case Stopped = 'stopped';
    case Running = 'running';
    case Break = 'break';
}
