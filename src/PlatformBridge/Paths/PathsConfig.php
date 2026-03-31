<?php

namespace Zoom\PlatformBridge\Paths;
/**
 * Value object pro konfiguraci cest načtenou z platformbridge.json.
 *
 * Uchovává validované cesty ke klíčovým souborům a adresářům projektu (assets, cache, api, security, bridge config).
 * Zajišťuje bezpečnostní validaci a jednotný přístup ke konfiguraci napříč projektem.
 *
 * Vytvářejte pouze přes PathsLoader.
 */
final class PathsConfig
{
    /**
     * Název chráněného konfiguračního souboru (.json.php s PHP exit guardem)
     */
    public const CONFIG_FILE_PROTECTED = 'platformbridge.json.php';

    /**
     * Název nechráněného konfiguračního souboru (čistý JSON)
     */
    public const CONFIG_FILE = 'platformbridge.json';

    // ─── Konfigurační klíče (single source of truth pro názvy klíčů) ─

    /** Klíč pro cestu k assets adresáři */
    public const KEY_ASSETS_PATH     = 'assets_path';
    /** Klíč pro cestu ke konfiguračnímu souboru bridge */
    public const KEY_BRIDGE_CONFIG   = 'bridge_config';
    /** Klíč pro cestu ke konfiguračnímu souboru zabezpečení */
    public const KEY_SECURITY_CONFIG = 'security_config';
    /** Klíč pro cestu k adresáři cache */
    public const KEY_CACHE_PATH      = 'cache_path';
    /** Klíč pro cestu k API souboru */
    public const KEY_API_FILE        = 'api_file';

    /** Výchozí stub pro api.php */
    public const STUB_API      = 'api.php';
    /** Výchozí stub pro bridge-config.php */
    public const STUB_BRIDGE   = 'bridge-config.php';
    /** Výchozí stub pro security-config.php */
    public const STUB_SECURITY = 'security-config.php';

    /**
     * Validované konfigurační pole s cestami (klíč => hodnota)
     * @var array<string, string>
     */
    private readonly array $config;

    /**
     * Vytvoří objekt s validovanou konfigurací cest.
     *
     * @param array<string, string> $defaults Výchozí hodnoty cest
     * @param array<string, mixed>  $data     Načtená data z JSON souboru
     */
    public function __construct(
        /** Výchozí hodnoty cest */
        private readonly array $defaults,
        /** Načtená data z JSON */
        private readonly array $data
    ) {
        $loaded = $this->validateData($data);
        // Sloučí výchozí hodnoty s validovanými načtenými hodnotami
        $this->config = array_merge($this->defaults, array_intersect_key($loaded, $this->defaults));
    }

    /**
     * Vrací relativní cestu k assets adresáři.
     */
    public function assets(): string
    {
        return $this->config[self::KEY_ASSETS_PATH];
    }

    /**
     * Vrací relativní cestu k adresáři cache.
     */
    public function cache(): string
    {
        return $this->config[self::KEY_CACHE_PATH];
    }

    /**
     * Vrací relativní cestu k API souboru.
     */
    public function api(): string
    {
        return $this->config[self::KEY_API_FILE];
    }

    /**
     * Vrací relativní cestu ke konfiguračnímu souboru zabezpečení.
     */
    public function security(): string
    {
        return $this->config[self::KEY_SECURITY_CONFIG];
    }

    /**
     * Vrací relativní cestu ke konfiguračnímu souboru bridge.
     */
    public function bridge(): string
    {
        return $this->config[self::KEY_BRIDGE_CONFIG];
    }

    // ─── Validation ──────────────────────────────────────────

    /**
     * Zvaliduje načtená data z JSON konfigurace.
     *
     * Validace:
     *   1. Filtruje meta klíče ($schema, $comment)
     *   2. Neznámé klíče → varování (tolerantní, ale informuje)
     *   3. Všechny hodnoty musí být stringy
     *   4. Cesty jsou validovány proti path traversal (../, absolutní cesty, null bytes)
     *
     * @param array<string, mixed> $data
     * @return array<string, string> Validované konfigurační pole
     * @throws \RuntimeException Pokud data obsahují nevalidní hodnoty
     */
    private function validateData(array $data): array
    {
        // Filtrovat meta klíče ($schema, $comment) – nejsou konfigurační
        $loaded = array_filter($data, static fn(string $key) => !str_starts_with($key, '$'), ARRAY_FILTER_USE_KEY);

        $configBasename = self::CONFIG_FILE;

        // Neznámé klíče → varování
        $unknown = array_diff_key($loaded, $this->defaults);
        if ($unknown !== []) {
            trigger_error(
                $configBasename . ': unknown keys ignored: ' . implode(', ', array_keys($unknown)),
                E_USER_NOTICE
            );
        }

		// Validace hodnot a bezpečnosti v jednom průchodu
		foreach ($loaded as $key => $value) {
			if (!isset($this->defaults[$key])) {
				continue;
			}
			if (!is_string($value)) {
				throw new \RuntimeException(
					$configBasename . ": key '{$key}' must be a string, got " . gettype($value)
				);
			}
			$this->validatePathSecurity($key, $value);
		}

        return $loaded;
    }

    /**
     * Validuje bezpečnost cesty – blokuje path traversal a absolutní cesty.
     *
     * Zakázané vzory:
     *   - ".." (parent directory traversal)
     *   - Absolutní cesty (začínající / nebo C:\)
     *   - Null bytes
     *
     * @param string $key   Název konfiguračního klíče
     * @param string $value Hodnota cesty
     * @throws \RuntimeException Pokud cesta obsahuje nebezpečný vzor
     */
    private function validatePathSecurity(string $key, string $value): void
    {
        // Path traversal (../ nebo ..\)
        $normalized = str_replace('\\', '/', $value);
        if (str_contains($normalized, '../') || str_contains($normalized, '..\\') || $normalized === '..') {
            throw new \RuntimeException(
                "Config: key '{$key}' contains path traversal ('..') – not allowed. "
                . 'Use only relative paths within the project root.'
            );
        }

        // Absolutní cesty (Linux /path nebo Windows C:\path)
        if (str_starts_with($normalized, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $value)) {
            throw new \RuntimeException(
                "Config: key '{$key}' must be a relative path, got absolute: '{$value}'. "
                . 'All paths are relative to the project root.'
            );
        }
    }
}
