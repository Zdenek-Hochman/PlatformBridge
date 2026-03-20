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
    protected ?string $variantKey;

    /** ConfigManager pro načítání pravidel z JSON */
    protected ?ConfigManager $configManager = null;

    /**
     * Single-key mód: pokud je nastaveno, generuje se pouze tento klíč.
     * Využívá data z relace místo přímého formuláře.
     */
    protected ?string $singleKey = null;

    /**
     * Vrátí název endpointu pro API
     */
    abstract protected function getEndpoint(): string;

    /**
     * Vrátí očekávaný typ odpovědi
     */
    abstract protected function getResponseType(): string;

    /**
     * Vrátí šablonu pro renderování odpovědi
     */
    abstract protected function getTemplate(): string;

    /**
     * Vrátí ID generátoru v konfiguraci (pro načtení required polí)
     * Přetíž v potomcích pro specifický generátor
     */
    abstract protected function getGeneratorId(): ?string;

	/**
	 * Transformuje vstupní data podle varianty nebo kontextu.
	 *
	 * Přetíž v potomcích pro specifickou logiku transformace vstupu (např. podle varianty, typu nebo dalších parametrů).
	 *
	 * @param array $input Vstupní data (pole s hodnotami).
	 * @param mixed ...$context Další kontextové parametry pro transformaci (např. varianta, typ, apod.).
	 * @return array Upravená vstupní data po transformaci.
	 */
    abstract protected function transformInput(array $input, mixed ...$context): array;

	/**
	 * Nastaví instanci správce konfigurace.
	 *
	 * @param ConfigManager $configManager Instance správce konfigurace.
	 * @return self Vrací aktuální instanci třídy pro řetězení metod.
	 */
    public function setConfigManager(ConfigManager $configManager): self
    {
        $this->configManager = $configManager;
        return $this;
    }
    /**
     * Přepne endpoint do single-key módu.
     *
     * V tomto režimu:
     * - Do AI API se posílá informace, který klíč se má generovat
     * - Odpověď se parsuje jako prostý string
     * - Použije se jednoduchá šablona (šetří tokeny)
     *
     * @param string $key Klíč k regeneraci (např. "subject", "preheader")
	 * @return self Vrací aktuální instanci pro řetězení metod
     */
    public function setSingleKeyMode(string $key): self
    {
        $this->singleKey = $key;
        return $this;
    }

	/**
	 * Určuje, zda je endpoint v single-key módu.
	 * Vrací true, pokud je nastaven klíč pro single-key generování, jinak false.
	 *
	 * @return bool True pokud je aktivní single-key mód, jinak false.
	 */
    private function isSingleKeyMode(): bool
    {
        return $this->singleKey !== null;
    }

	/**
	 * Vrátí název klíče pro single-key regeneraci.
	 * Pokud není nastaven single-key mód, vrací hodnotu null.
	 *
	 * @return string|null Název klíče nebo null, pokud není nastaven single-key mód.
	 */
    public function getSingleKey(): ?string
    {
        return $this->singleKey;
    }

	/**
	 * Vrátí aktivní šablonu pro renderování odpovědi.
	 *
	 * Pokud je endpoint v single-key módu, použije jednoduchou šablonu {@see SINGLE_KEY_TEMPLATE}.
	 * Jinak vrací šablonu definovanou v potomcích.
	 *
	 * @return string Název šablony pro renderování odpovědi.
	 */
    public function getActiveTemplate(): string
    {
        if ($this->isSingleKeyMode()) {
            return self::SINGLE_KEY_TEMPLATE;
        }
        return $this->getTemplate();
    }

	/**
	 * Vrátí aktivní typ odpovědi pro endpoint.
	 *
	 * Pokud je endpoint v single-key módu, vrací vždy typ odpovědi "string".
	 * Jinak vrací typ odpovědi definovaný v potomcích.
	 *
	 * @return string Typ odpovědi (např. "string", "array", "nested").
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
	 * @return string HTTP metoda pro endpoint (např. "POST", "GET")
     */
    public function getMethod(): string
    {
        return 'POST';
    }

	/**
	 * Vrátí mapování názvů polí na AI klíče podle konfigurace generátoru.
	 *
	 * Pro daný generátor načte sekce a bloky z konfigurace a vytvoří mapu [název_pole => ai_klíč].
	 * Pokud není generátor nebo jeho layout dostupný, vrací prázdné pole.
	 *
	 * @return array<string, string> Mapování názvů polí na AI klíče (field_name => ai_key).
	 */
    private function getFieldToAiKeyMapping(): array
    {
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
	 * Detekuje variantu vstupu podle nastaveného klíče variantKey.
	 *
	 * Z inputu načte hodnotu pod klíčem variantKey a vrátí ji jako název varianty.
	 * Pokud není klíč nastaven nebo je hodnota prázdná, vrací null.
	 *
	 * @param array $input Vstupní data (pole s hodnotami).
	 * @return string|null Název varianty nebo null, pokud není varianta určena.
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
	 * Transformuje vstupní pole na AI klíče podle konfigurace.
	 *
	 * Pro každý název pole ve vstupním poli najde odpovídající AI klíč z mapování
	 * a vytvoří nové pole s těmito klíči. Pokud není mapování nalezeno, použije původní název pole.
	 *
	 * @param array $input Vstupní data s názvy polí (field names).
	 * @return array Výstupní data s AI klíči.
	 */
    private function transformToAiKeys(array $input): array
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
	 * Vytvoří instanci AiRequest z předaných dat.
	 *
	 * Detekuje variantu vstupu, případně transformuje data podle varianty.
	 * V single-key módu nastaví speciální parametry pro generování pouze jednoho klíče.
	 * Sloučí vstupní data s parametry pro tělo požadavku a nastaví parametry pro dotaz.
	 *
	 * @param array $input Vstupní data pro požadavek (pole s hodnotami).
	 * @param array $queryParams Parametry pro dotaz (query string), výchozí je prázdné pole.
	 * @param array $bodyParams Parametry pro tělo požadavku, výchozí je prázdné pole.
	 * @return AiRequest Instanci požadavku pro AI API.
	 */
    public function createRequest(array $input, array $queryParams = [], array $bodyParams = []): AiRequest
    {
        $variant = $this->detectVariant($input);

        if ($variant !== null) {
            $input = $this->transformInput($input, $variant);
        }

        $transformed = $this->transformToAiKeys($input);

        // Single-key mód: přidá informaci o klíči do body payloadu
        // AI API pak generuje pouze tento jeden klíč → šetří tokeny
        if ($this->isSingleKeyMode()) {
            $bodyParams['generate_key'] = $this->singleKey;
            $bodyParams['request_amount'] = 1;
        }

        // Merge bodyParams do inputu (bodyParams mají vyšší prioritu)
        return AiRequest::to($this->getEndpoint())
            ->withQueryParams($queryParams)
            ->withPrompt(array_merge($transformed, $bodyParams))
            ->usingMethod($this->getMethod());
    }

	/**
	 * Parsuje odpověď podle aktivního typu odpovědi.
	 *
	 * Na základě typu odpovědi (string, array, nested) zavolá odpovídající metodu pro parsování dat.
	 * V single-key módu vždy parsuje jako string.
	 *
	 * @param mixed $data Data k parsování (může být string, pole nebo jiný typ).
	 * @return mixed Parsovaná odpověď podle typu (string, array nebo jiný typ).
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

	/**
	 * Parsuje data jako string.
	 *
	 * Pokud jsou data typu string, vrací je přímo.
	 * Pokud jsou data pole a obsahují klíč 'text' nebo 'content', vrací jeho hodnotu jako string.
	 * V ostatních případech vrací data převedená na string.
	 *
	 * @param mixed $data Data k parsování (string nebo pole).
	 * @return string Parsovaná hodnota jako string.
	 */
    private function parseAsString(mixed $data): string
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

	/**
	 * Parsuje data jako pole (array).
	 *
	 * Pokud jsou data typu array, vrací je přímo.
	 * Pokud jsou data typu string, pokusí se je dekódovat jako JSON a vrátí výsledek,
	 * nebo vrátí pole s hodnotou pod klíčem 'value'.
	 * V ostatních případech vrací pole s hodnotou pod klíčem 'value'.
	 *
	 * @param mixed $data Data k parsování (array, string nebo jiný typ).
	 * @return array Parsovaná data jako pole.
	 */
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

	/**
	 * Parsuje data jako pole v poli (nested array).
	 *
	 * Nejprve data převede na pole pomocí parseAsArray().
	 * Pokud je pole prázdné, vrací prázdné pole.
	 * Pokud první prvek není pole, obalí výsledek do pole (array v array).
	 * Jinak vrací původní pole.
	 *
	 * @param mixed $data Data k parsování (array, string nebo jiný typ).
	 * @return array Parsovaná data jako pole v poli (nested array).
	 */
    private function parseAsNested(mixed $data): array
    {
        $parsed = $this->parseAsArray($data);

        if (empty($parsed)) {
            return [];
        }

        $firstKey = array_key_first($parsed);

        if (!is_array($parsed[$firstKey] ?? null)) {
            return [$parsed];
        }

        return $parsed;
    }
}