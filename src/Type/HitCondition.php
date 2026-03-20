<?php

declare(strict_types=1);

namespace DbgpClient\Type;

enum HitCondition: string
{
    case Equal = '==';
    case GreaterOrEqual = '>=';
    case Modulo = '%';
}
