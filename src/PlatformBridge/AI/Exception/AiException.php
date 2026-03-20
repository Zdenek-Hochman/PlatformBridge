<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\AI\Exception;

use Exception;
use Throwable;

/**
 * Základní výjimka pro AI operace
 */
class AiException extends Exception
{
    public const ERROR_INVALID_REQUEST = 1001;
    public const ERROR_VALIDATION = 1002;
    public const ERROR_CONNECTION = 1003;
    public const ERROR_TIMEOUT = 1004;
    public const ERROR_INVALID_RESPONSE = 1005;
    public const ERROR_API = 1006;

    protected array $context = [];

    public function __construct(string $message, int $code = 0, ?Throwable $previous = null, array $context = []) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function invalidRequest(string $reason, array $context = []): self
    {
        return new self("Neplatný request: {$reason}", self::ERROR_INVALID_REQUEST, null, $context);
    }

    public static function connectionFailed(string $error, array $context = []): self
    {
        return new self("Chyba připojení: {$error}", self::ERROR_CONNECTION, null, $context);
    }

    public static function timeout(int $seconds, array $context = []): self
    {
        return new self("Požadavek vypršel po {$seconds} sekundách", self::ERROR_TIMEOUT, null, $context);
    }

    public static function invalidResponse(string $reason, array $context = []): self
    {
        return new self("Neplatná odpověď: {$reason}", self::ERROR_INVALID_RESPONSE, null, $context);
    }

    public static function apiError(string $message, int $statusCode = 0, array $context = []): self
    {
        return new self("API chyba: {$message}", self::ERROR_API, null, array_merge(['status_code' => $statusCode], $context));
    }
}
