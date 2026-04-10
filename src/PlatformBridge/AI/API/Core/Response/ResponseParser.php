<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Core\Response;

use PlatformBridge\AI\API\Enum\ResponseType;

/**
 * Parsuje surová data z AI API odpovědi podle typu ResponseType.
 *
 * Strategie:
 * - String: extrahuje text/content z pole
 * - Array:  dekóduje JSON string na pole
 * - Nested: zajistí strukturu array-in-array
 */
final class ResponseParser
{
    public function parse(mixed $data, ResponseType $responseType): mixed
    {
        return match ($responseType) {
            ResponseType::String => $this->parseAsString($data),
            ResponseType::Array  => $this->parseAsArray($data),
            ResponseType::Nested => $this->parseAsNested($data),
            default              => $data,
        };
    }

    private function parseAsString(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_array($data)) {
            return (string) ($data['text'] ?? $data['content'] ?? '');
        }

        return (string) ($data ?? '');
    }

    private function parseAsArray(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : ['value' => $data];
        }

        return ['value' => $data];
    }

    private function parseAsNested(mixed $data): array
    {
        $parsed = $this->parseAsArray($data);

        if ($parsed === []) {
            return [];
        }

        $firstKey = array_key_first($parsed);

        return is_array($parsed[$firstKey] ?? null) ? $parsed : [$parsed];
    }
}
