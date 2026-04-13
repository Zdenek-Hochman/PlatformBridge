<?php

declare(strict_types=1);

namespace PlatformBridge\Container;

/**
 * Výjimka pro chyby DI kontejneru.
 */
final class ContainerException extends \RuntimeException
{
    public static function serviceNotFound(string $id): self
    {
        return new self("Služba '{$id}' není registrována v kontejneru.");
    }
}
