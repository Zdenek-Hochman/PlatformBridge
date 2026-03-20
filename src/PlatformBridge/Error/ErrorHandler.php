<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Error;

/**
 * Zachytává PHP chyby, neošetřené výjimky a fatální shutdown chyby.
 *
 * Handler **nezná** vzhled – veškerý výstup deleguje na {@see ErrorRenderer}.
 * Tím je zajištěno, že:
 *  - Systémové chyby (E_ERROR, E_WARNING, …) se převedou na ErrorException.
 *  - Aplikační výjimky implementující {@see RenderableException} dostanou vlastní vizuál.
 *  - V produkci se zobrazí pouze obecná hláška.
 */
final class ErrorHandler
{
    private ErrorRenderer $renderer;

    public function __construct(?ErrorRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new ErrorRenderer();
    }

    /**
     * Zaregistruje globální handlery pro výjimky, errory a shutdown.
     */
    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
    }

    /**
     * Zpracuje neošetřenou výjimku / Throwable.
     */
    public function handleException(\Throwable $e): void
    {
        $statusCode = $e instanceof RenderableException
            ? $e->getHttpStatusCode()
            : 500;

        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        $this->renderer->render($e);
    }

    /**
     * Převede PHP error na ErrorException a předá dál.
     */
    public function handleError(int $errno, string $errstr, ?string $errfile = null, ?int $errline = null): bool
    {
        $ex = new \ErrorException(
            $errstr,
            0,
            $errno,
            $errfile ?? '[internal]',
            $errline ?? 0,
        );

        $this->handleException($ex);

        return true;
    }

    /**
     * Zachytí fatální chyby při shutdown (E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE).
     */
    public function handleShutdown(): void
    {
        $err = error_get_last();

        if ($err === null) {
            return;
        }

        if (!in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            return;
        }

        $ex = new \ErrorException(
            $err['message'],
            0,
            $err['type'],
            $err['file'] ?? '[internal]',
            $err['line'] ?? 0,
        );

        $this->handleException($ex);
    }
}
