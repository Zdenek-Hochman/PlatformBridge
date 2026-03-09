<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\AI;

/**
 * Konfigurace AI klienta
 */
class AiClientConfig
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl = 'https://api.virtualzoom.com/v2/AI',
        // public readonly string $baseUrl = 'http://localhost/ai/src/PlatformBridge/AI/TEST',
        public readonly int $timeout = 30,
        public readonly int $connectTimeout = 10,
        public readonly int $maxRetries = 3,
        public readonly int $retryDelay = 1000, // v ms
        public readonly bool $verifySsl = true,
        public readonly bool $debug = false,
        public readonly array $defaultHeaders = []
    ) {}

    /**
     * Factory metoda pro vytvoření z pole
     */
    public static function fromArray(array $config): self
    {
        return new self(
            apiKey: $config['api_key'] ?? throw new \InvalidArgumentException('API key je povinný'),
            baseUrl: $config['base_url'] ?? 'http://localhost/ai/src/PlatformBridge/AI/TEST',
            // baseUrl: $config['base_url'] ?? 'https://api.virtualzoom.com/v2/AI',
            timeout: $config['timeout'] ?? 30,
            connectTimeout: $config['connect_timeout'] ?? 10,
            verifySsl: $config['verify_ssl'] ?? false,
            defaultHeaders: $config['default_headers'] ?? []
        );
    }

    /**
     * Vytvoří URL pro endpoint
     */
    public function buildUrl(string $endpoint, array $queryParams = []): string
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/') . '/';

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        return $url;
    }

    /**
     * Získá hlavičky pro request
     */
    public function getHeaders(array $additional = []): array
    {
        return array_merge([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->apiKey}",
            'Accept' => 'application/json',
        ], $this->defaultHeaders, $additional);
    }
}
