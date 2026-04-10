<?php

namespace PlatformBridge\AI\API\Enum;

enum HttpMethod: string
{
    case POST = 'POST';
    case GET = 'GET';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
}
