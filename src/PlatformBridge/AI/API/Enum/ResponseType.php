<?php

namespace PlatformBridge\AI\API\Enum;

enum ResponseType: string
{
    case String = 'string';
    case Array = 'array';
    case Nested = 'nested';
}