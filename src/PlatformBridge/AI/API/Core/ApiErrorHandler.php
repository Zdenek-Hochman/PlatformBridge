<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Core;

use PlatformBridge\AI\Exception\{
    AiException,
    JsonException
};
use PlatformBridge\Security\Exception\SecurityException;

/**
 * Centrální error handler pro API odpovědi.
 *
 * Zodpovídá za:
 *   - Formátování chybových odpovědí do jednotné JSON struktury
 *   - Mapování výjimek na HTTP status kódy
 *   - Mapování výjimek na typy chyb (security, invalid_json, ai_provider, ...)
 *   - Volitelné přidání trace v debug režimu
 *
 * Extrahováno z {@see ApiHandler} pro dodržení SRP –
 * ApiHandler zpracovává požadavky, ApiErrorHandler formátuje chyby.
 *
 * @see ApiHandler::handle() Používá sendError() pro zachycení výjimek
 */
final class ApiErrorHandler
{
    /**
     * Odešle chybovou odpověď ve formátu JSON a nastaví HTTP status kód.
     *
     * @param \Throwable $e Výjimka nebo chyba, která má být odeslána.
     */
    public static function sendError(\Throwable $e): void
    {
        $status = self::resolveHttpStatus($e);
        http_response_code($status);

        $error = [
            'type'    => self::resolveErrorType($e),
            'code'    => $e->getCode() ?: $status,
            'message' => $e->getMessage(),
        ];

        // AiException a JsonException nesou strukturovaný kontext
        if ($e instanceof AiException || $e instanceof JsonException) {
            $error['context'] = $e->getContext();
        }

        // V debug režimu přidat trace
        if (defined('DEBUG_MODE') && \constant('DEBUG_MODE')) {
            $error['trace'] = $e->getTraceAsString();
        }

        echo json_encode([
            'api' => [
                'success'     => false,
                'status_code' => $status,
                'error'       => $error,
            ],
            'provider' => null,
            'data'     => null,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Určí HTTP status kód na základě typu výjimky.
     *
     * @param \Throwable $e Výjimka, pro kterou se určuje status kód.
     * @return int Vrací odpovídající HTTP status kód.
     */
    private static function resolveHttpStatus(\Throwable $e): int
    {
        if ($e instanceof SecurityException) {
            return 403;
        }
        if ($e instanceof \JsonException) {
            return 500;
        }

        if ($e instanceof AiException) {
            return match ($e->getCode()) {
                AiException::ERROR_VALIDATION     => 422,
                AiException::ERROR_INVALID_REQUEST => 400,
                AiException::ERROR_TIMEOUT         => 504,
                AiException::ERROR_CONNECTION      => 502,
                default                            => 500,
            };
        }

        return 500;
    }

    /**
     * Určí typ chyby na základě typu výjimky.
     *
     * @param \Throwable $e Výjimka, pro kterou se určuje typ chyby.
     * @return string Vrací řetězec reprezentující typ chyby.
     */
    private static function resolveErrorType(\Throwable $e): string
    {
        return match (true) {
            $e instanceof SecurityException => 'security',
            $e instanceof \JsonException    => 'invalid_json',
            $e instanceof AiException       => 'ai_provider',
            default                         => 'internal_error',
        };
    }
}
