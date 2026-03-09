<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\AI;

/**
 * Vylepšená odpověď z AI API
 */
class AiResponse
{
    public function __construct(
        protected readonly mixed $response,
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

    public function getResponse(): mixed
    {
        return $this->response;
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
        $response = $this->response;

        if (!is_array($response)) {
            return $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($response) || !array_key_exists($segment, $response)) {
                return $default;
            }
            $response = $response[$segment];
        }

        return $response;
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

        return $this->response;
    }

    public function toArray(): array
    {
        return [
			'success' => $this->success,
			'status_code' => $this->statusCode,
            'meta' => $this->meta,
            'response' => $this->response,
            'error' => $this->error,
        ];
    }

    /**
     * Vytvoří úspěšnou odpověď z API
     */
    public static function fromApi(array $response, int $statusCode = 200): self
    {
        $payload = $response['data'] ?? $response['result'] ?? $response;
        $success = ($payload['success'] ?? true) && $statusCode >= 200 && $statusCode < 300;
        $error = $payload['error'] ?? $payload['message'] ?? null;

        $metaKeys = ['request_id', 'timestamp', 'usage'];

		$meta = array_filter(array_intersect_key($response, array_flip($metaKeys)));
		// $payload = array_diff_key($payload, array_flip($metaKeys));

        return new self(
            response: $payload,
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
            response: null,
            raw: $raw,
            statusCode: $statusCode,
            success: false,
            error: $message
        );
    }
}
