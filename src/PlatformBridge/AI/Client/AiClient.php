<?php

declare(strict_types=1);

namespace PlatformBridge\AI\Client;

use PlatformBridge\AI\Exception\AiException;

/**
 * AI klient pro komunikaci s API
 */
class AiClient
{
    public function __construct(protected readonly AiClientConfig $config)
    {
    }

    /**
     * Odešle request
     */
    public function send($request): AiResponse
    {
        // GET parametry jdou do URL, BODY parametry do payloadu
        $url = $this->config->buildUrl($request->getEndpoint(), $request->getQueryParams());
        return $this->executeRequest($url, $request);
    }

    /**
     * Vykoná HTTP request
     */
    protected function executeRequest(string $url, $request): AiResponse
    {
        $ch = curl_init($url);

        $headers = $this->config->getHeaders($request->getHeaders());

        $formattedHeaders = array_map(fn ($key, $value) => "{$key}: {$value}", array_keys($headers), array_values($headers));

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_TIMEOUT => $this->config->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->config->connectTimeout,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_POSTFIELDS => json_encode((array)$request->toPayload(), JSON_UNESCAPED_UNICODE),
            CURLOPT_SSL_VERIFYPEER => $this->config->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->config->verifySsl ? 2 : 0,
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);

            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw AiException::timeout($this->config->timeout);
            }

            throw AiException::connectionFailed($error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $body = substr($response, $headerSize);

        if (empty($body)) {
            return AiResponse::error('Prázdná odpověď', $statusCode);
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw AiException::invalidResponse("Nelze dekódovat JSON: {$e->getMessage()}");
        }

        return AiResponse::fromApi($decoded, $statusCode);
    }
}
