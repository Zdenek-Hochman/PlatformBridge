<?php

declare(strict_types=1);

namespace PlatformBridge\AI\Exception;

use PlatformBridge\Shared\Exception\JsonException as BaseJsonException;
use Throwable;

/**
 * JSON výjimka specifická pro AI modul.
 *
 * Rozšiřuje sdílenou {@see BaseJsonException} o pole kontextu,
 * které se používá v {@see \PlatformBridge\AI\API\Core\ApiHandler}
 * pro strukturované chybové odpovědi.
 */
class JsonException extends BaseJsonException
{
    protected array $context = [];

    public function __construct(string $message, int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, null, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function invalidJson(string $message, \JsonException $exception, array $context = []): self
    {
        return new self(
            $message,
            self::mapNativeCode($exception->getCode()),
            $exception,
            array_merge(['status_code' => $exception->getCode()], $context),
        );
    }

    /**
     * Mapuje nativní PHP JSON chybový kód na kód této výjimky.
     */
    private static function mapNativeCode(int $nativeCode): int
    {
        return match ($nativeCode) {
            JSON_ERROR_DEPTH => self::CODE_DEPTH_EXCEEDED,
            JSON_ERROR_UTF8  => self::CODE_UTF8_ERROR,
            default          => self::CODE_SYNTAX_ERROR,
        };
    }
}
