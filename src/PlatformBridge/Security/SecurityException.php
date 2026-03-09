<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Security;

/**
 * Výjimka pro bezpečnostní chyby (neplatný podpis, expirace, atd.)
 */
class SecurityException extends \Exception
{
}
