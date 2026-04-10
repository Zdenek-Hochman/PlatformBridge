<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Core\Endpoint;

use PlatformBridge\AI\Client\AiRequest;
use PlatformBridge\AI\API\Enum\ResponseType;
use PlatformBridge\AI\API\Core\Response\ResponseParser;
use PlatformBridge\Config\ConfigManager;

/**
 * Abstraktní definice endpointu.
 *
 * Každý endpoint definuje:
 * - Jaké vstupní varianty akceptuje (např. template vs custom)
 * - Jaký typ odpovědi očekává (string, array, nested)
 * - Jakou šablonu použít pro renderování
 *
 * Required pole jsou dynamicky načítána z JSON konfigurace (blocks.json),
 * nikoliv hardcoded v kódu endpointu.
 */
abstract class EndpointDefinition
{
    public const SINGLE_KEY_TEMPLATE = '/Components/SingleKeyResult';

    protected ?string $variantKey;
    protected ?ConfigManager $configManager = null;
    protected ?string $singleKey = null;

    // ── Abstrakt ────────────────────────────────────────────────

    abstract protected function getEndpoint(): string;
    abstract protected function getResponseType(): ResponseType;
    abstract protected function getTemplate(): string;
    abstract protected function getGeneratorId(): ?string;
    abstract protected function transformInput(array $input, mixed ...$context): array;

    // ── ConfigManager ───────────────────────────────────────────

    public function setConfigManager(ConfigManager $configManager): self
    {
        $this->configManager = $configManager;
        return $this;
    }

    // ── Single-key mód ──────────────────────────────────────────

    /**
     * Přepne endpoint do single-key módu.
     * Do AI API se posílá jen jeden klíč, odpověď je prostý string.
     */
    public function setSingleKeyMode(string $key): self
    {
        $this->singleKey = $key;
        return $this;
    }

    public function getSingleKey(): ?string
    {
        return $this->singleKey;
    }

    public function getActiveTemplate(): string
    {
        return $this->isSingleKeyMode() ? self::SINGLE_KEY_TEMPLATE : $this->getTemplate();
    }

    public function getActiveResponseType(): ResponseType
    {
        return $this->isSingleKeyMode() ? ResponseType::String : $this->getResponseType();
    }

    private function isSingleKeyMode(): bool
    {
        return $this->singleKey !== null;
    }

    // ── Varianta ────────────────────────────────────────────────

    public function detectVariant(array $input): ?string
    {
        if ($this->variantKey === null) {
            return null;
        }

        $value = $input[$this->variantKey] ?? null;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    // ── Request / Response ──────────────────────────────────────

    public function createRequest(array $input, array $queryParams = [], array $bodyParams = []): AiRequest
    {
        $variant = $this->detectVariant($input);

        if ($variant !== null) {
            $input = $this->transformInput($input, $variant);
        }

        $transformed = (new FieldMapper($this->configManager))->transformToAiKeys($input, $this->getGeneratorId());

        if ($this->isSingleKeyMode()) {
            $bodyParams['generate_key'] = $this->singleKey;
            $bodyParams['request_amount'] = 1;
        }

        return AiRequest::to($this->getEndpoint())
            ->withQueryParams($queryParams)
            ->withPrompt(array_merge($transformed, $bodyParams))
            ->usingMethod('POST');
    }

    public function parseResponse(mixed $data): mixed
    {
        return (new ResponseParser())->parse($data, $this->getActiveResponseType());
    }
}
