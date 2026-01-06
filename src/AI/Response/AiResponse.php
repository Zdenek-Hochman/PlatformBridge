<?php

namespace AI\Response;

use AI\AiException;

/**
 * Vylepšená odpověď z AI API
 */
class AiResponse implements AiResponseInterface
{
    public function __construct(
        protected readonly mixed $data,
        protected readonly array $raw,
        protected readonly int $statusCode = 200,
        protected readonly bool $success = true,
        protected readonly ?string $error = null,
        protected readonly array $meta = []
    ) {}

    public function isSuccess(): bool
    {
        return $this->success && $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Získá konkrétní hodnotu z dat pomocí tečkové notace
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->data;

        if (!is_array($data)) {
            return $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * Získá data nebo vyhodí výjimku
     */
    public function getOrFail(): mixed
    {
        if (!$this->isSuccess()) {
            throw AiException::apiError(
                $this->error ?? 'Neznámá chyba',
                $this->statusCode,
                ['raw' => $this->raw]
            );
        }

        return $this->data;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status_code' => $this->statusCode,
            'data' => $this->data,
            'error' => $this->error,
            'meta' => $this->meta
        ];
    }

    /**
     * Vytvoří úspěšnou odpověď z API
     */
    public static function fromApi(array $response, int $statusCode = 200): self
    {
        $data = $response['data'] ?? $response['result'] ?? $response;
        $success = ($response['success'] ?? true) && $statusCode >= 200 && $statusCode < 300;
        $error = $response['error'] ?? $response['message'] ?? null;

        $meta = array_filter([
            'request_id' => $response['request_id'] ?? null,
            'timestamp' => $response['timestamp'] ?? null,
            'usage' => $response['usage'] ?? null,
        ]);

        return new self(
            data: $data,
            raw: $response,
            statusCode: $statusCode,
            success: $success,
            error: is_string($error) ? $error : null,
            meta: $meta
        );
    }

    /**
     * Vytvoří chybovou odpověď
     */
    public static function error(string $message, int $statusCode = 400, array $raw = []): self
    {
        return new self(
            data: null,
            raw: $raw,
            statusCode: $statusCode,
            success: false,
            error: $message
        );
    }

    /**
     * Mapuje data pomocí callbacku
     */
    public function map(callable $callback): self
    {
        if (!$this->isSuccess()) {
            return $this;
        }

        return new self(
            data: $callback($this->data),
            raw: $this->raw,
            statusCode: $this->statusCode,
            success: $this->success,
            error: $this->error,
            meta: $this->meta
        );
    }
}
