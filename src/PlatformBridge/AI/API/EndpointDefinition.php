<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\AI\API;

use Zoom\PlatformBridge\AI\AiRequest;
use Zoom\PlatformBridge\Config\ConfigManager;
use Zoom\PlatformBridge\Config\ConfigKeys;

/**
 * Abstraktní definice endpointu
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
    /** Typy odpovědí */
    public const RESPONSE_STRING = 'string';
    public const RESPONSE_ARRAY = 'array';
    public const RESPONSE_NESTED = 'nested'; // array v array

    /** Šablona pro single-key odpověď */
    public const SINGLE_KEY_TEMPLATE = '/Components/SingleKeyResult';

    /**
     * Klíč v inputu, podle kterého se určuje varianta
     */
    protected ?string $variantKey = 'topic_source';

    /** ConfigManager pro načítání pravidel z JSON */
    protected ?ConfigManager $configManager = null;

    /** Cache pro required pole z konfigurace */
    private ?array $cachedRequiredFields = null;

    /**
     * Single-key mód: pokud je nastaveno, generuje se pouze tento klíč.
     * Využívá data z relace místo přímého formuláře.
     */
    protected ?string $singleKey = null;

    /**
     * Vrátí název endpointu pro API
     */
    abstract public function getEndpoint(): string;

    /**
     * Vrátí očekávaný typ odpovědi
     */
    abstract public function getResponseType(): string;

    /**
     * Vrátí šablonu pro renderování odpovědi
     */
    abstract public function getTemplate(): string;

    /**
     * Vrátí ID generátoru v konfiguraci (pro načtení required polí)
     * Přetíž v potomcích pro specifický generátor
     */
    abstract public function getGeneratorId(): ?string;

    /**
     * Nastaví ConfigManager pro dynamické načítání pravidel
     */
    public function setConfigManager(ConfigManager $configManager): self
    {
        $this->configManager = $configManager;
        $this->cachedRequiredFields = null; // reset cache
        return $this;
    }

    // =========================================================================
    // SINGLE-KEY MÓD
    // =========================================================================

    /**
     * Přepne endpoint do single-key módu.
     *
     * V tomto režimu:
     * - Do AI API se posílá informace, který klíč se má generovat
     * - Odpověď se parsuje jako prostý string
     * - Použije se jednoduchá šablona (šetří tokeny)
     *
     * @param string $key Klíč k regeneraci (např. "subject", "preheader")
     */
    public function setSingleKeyMode(string $key): self
    {
        $this->singleKey = $key;
        return $this;
    }

    /**
     * Vrátí, zda je endpoint v single-key módu.
     */
    public function isSingleKeyMode(): bool
    {
        return $this->singleKey !== null;
    }

    /**
     * Vrátí název klíče pro single-key regeneraci.
     */
    public function getSingleKey(): ?string
    {
        return $this->singleKey;
    }

    /**
     * Vrátí aktivní šablonu — v single-key módu jednoduchá textová šablona.
     */
    public function getActiveTemplate(): string
    {
        if ($this->isSingleKeyMode()) {
            return self::SINGLE_KEY_TEMPLATE;
        }
        return $this->getTemplate();
    }

    /**
     * Vrátí aktivní typ odpovědi — v single-key módu vždy STRING.
     */
    public function getActiveResponseType(): string
    {
        if ($this->isSingleKeyMode()) {
            return self::RESPONSE_STRING;
        }
        return $this->getResponseType();
    }

    /**
     * Vrátí HTTP metodu (výchozí POST)
     */
    public function getMethod(): string
    {
        return 'POST';
    }

    /**
     * Vrátí required pole z JSON konfigurace.
     *
     * Načítá z blocks.json podle layoutu přiřazeného generátoru.
     * Pole je required pokud má v rules: { "required": true }
     *
     * @return array<string> Seznam názvů (name) povinných polí
     */
    public function getRequiredFieldsFromConfig(): array
    {
        if ($this->cachedRequiredFields !== null) {
            return $this->cachedRequiredFields;
        }

        $this->cachedRequiredFields = [];

        if ($this->configManager === null) {
            return $this->cachedRequiredFields;
        }

        $generatorId = $this->getGeneratorId();
        if ($generatorId === null) {
            return $this->cachedRequiredFields;
        }

        $generator = $this->configManager->findGenerator($generatorId);
        if ($generator === null || !isset($generator['layout'])) {
            return $this->cachedRequiredFields;
        }

        // Projdi všechny sekce a bloky v layoutu
        $sections = $generator['layout'][ConfigKeys::SECTIONS->value] ?? [];

        foreach ($sections as $section) {
            $blocks = $section[ConfigKeys::BLOCKS->value] ?? [];

            foreach ($blocks as $block) {
                $rules = $block[ConfigKeys::RULES->value] ?? [];
                $isRequired = $rules[ConfigKeys::REQUIRED->value] ?? false;

                if ($isRequired === true) {
                    // Použij 'name' jako identifikátor pole (to přijde z formuláře)
                    $fieldName = $block[ConfigKeys::NAME->value] ?? $block[ConfigKeys::ID->value] ?? null;
                    if ($fieldName !== null) {
                        $this->cachedRequiredFields[] = $fieldName;
                    }
                }
            }
        }

        return $this->cachedRequiredFields;
    }

    /**
     * Vrátí mapování field name -> ai_key z konfigurace.
     *
     * @return array<string, string> [field_name => ai_key]
     */
    public function getFieldToAiKeyMapping(): array
    {
        if ($this->configManager === null) {
            return [];
        }

        $generatorId = $this->getGeneratorId();
        if ($generatorId === null) {
            return [];
        }

        $generator = $this->configManager->findGenerator($generatorId);
        if ($generator === null || !isset($generator['layout'])) {
            return [];
        }

        $mapping = [];
        $sections = $generator['layout'][ConfigKeys::SECTIONS->value] ?? [];

        foreach ($sections as $section) {
            $blocks = $section[ConfigKeys::BLOCKS->value] ?? [];

            foreach ($blocks as $block) {
                $fieldName = $block[ConfigKeys::NAME->value] ?? null;
                $aiKey = $block[ConfigKeys::AI_KEY->value] ?? null;

                if ($fieldName !== null && $aiKey !== null) {
                    $mapping[$fieldName] = $aiKey;
                }
            }
        }

        return $mapping;
    }

    /**
     * Detekuje variantu vstupu podle nastaveného klíče (variantKey).
     * Hodnota z inputu pod tímto klíčem = název varianty.
     */
    public function detectVariant(array $input): ?string
    {
        if ($this->variantKey === null) {
            return null;
        }

        $value = $input[$this->variantKey] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * Zkontroluje, zda vstup odpovídá pravidlům varianty
     */
    protected function matchesVariant(array $input, array $rules): bool
    {
        $requiredFields = $rules['required'] ?? [];
        $forbiddenFields = $rules['forbidden'] ?? [];

        // Musí obsahovat všechny required
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || $input[$field] === '' || $input[$field] === null) {
                return false;
            }
        }

        // Nesmí obsahovat forbidden (pokud jsou definovány)
        foreach ($forbiddenFields as $field) {
            if (isset($input[$field]) && $input[$field] !== '' && $input[$field] !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Transformuje vstupní pole na AI klíče podle konfigurace.
     *
     * @param array $input Vstupní data s field names
     * @return array Data s AI klíči
     */
    public function transformToAiKeys(array $input): array
    {
        $mapping = $this->getFieldToAiKeyMapping();
        $transformed = [];

        foreach ($input as $fieldName => $value) {
            $aiKey = $mapping[$fieldName] ?? $fieldName;
            $transformed[$aiKey] = $value;
        }

        return $transformed;
    }

    /**
     * Transformuje vstupní data podle varianty
     * Přetíž v potomcích pro specifickou logiku
     */
    public function transformInput(array $input, string $variant): array
    {
        return $input;
    }

    /**
     * Vytvoří AiRequest z dat
     */
    public function createRequest(array $input, array $queryParams = [], array $bodyParams = []): AiRequest
    {
        $variant = $this->detectVariant($input);

        if ($variant !== null) {
            $input = $this->transformInput($input, $variant);
        }

        // Single-key mód: přidá informaci o klíči do body payloadu
        // AI API pak generuje pouze tento jeden klíč → šetří tokeny
        if ($this->isSingleKeyMode()) {
            $bodyParams['generate_key'] = $this->singleKey;
            $bodyParams['request_amount'] = 1;
        }

        // Merge bodyParams do inputu (bodyParams mají vyšší prioritu)
        return AiRequest::to($this->getEndpoint())
            ->withQueryParams($queryParams)
            ->withPrompt(array_merge($input, $bodyParams))
            ->usingMethod($this->getMethod());
    }

    /**
     * Parsuje odpověď podle očekávaného typu.
     * V single-key módu vždy parsuje jako string.
     */
    public function parseResponse(mixed $data): mixed
    {
        return match ($this->getActiveResponseType()) {
            self::RESPONSE_STRING => $this->parseAsString($data),
            self::RESPONSE_ARRAY => $this->parseAsArray($data),
            self::RESPONSE_NESTED => $this->parseAsNested($data),
            default => $data,
        };
    }

    protected function parseAsString(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }
        if (is_array($data) && isset($data['text'])) {
            return (string) $data['text'];
        }
        if (is_array($data) && isset($data['content'])) {
            return (string) $data['content'];
        }
        return (string) ($data ?? '');
    }

    protected function parseAsArray(mixed $data): array
    {
        if (is_array($data)) {
            // Pokud je to asociativní pole nebo indexované, vrátíme jak je
            return $data;
        }
        if (is_string($data)) {
            // Zkusíme parsovat jako JSON
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : ['value' => $data];
        }
        return ['value' => $data];
    }

    protected function parseAsNested(mixed $data): array
    {
        $parsed = $this->parseAsArray($data);

        // Zajistíme, že máme array v array
        if (empty($parsed)) {
            return [];
        }

        // Pokud první element není array, obalíme
        $firstKey = array_key_first($parsed);

        if (!is_array($parsed[$firstKey] ?? null)) {
            return [$parsed];
        }

        return $parsed;
    }
}
