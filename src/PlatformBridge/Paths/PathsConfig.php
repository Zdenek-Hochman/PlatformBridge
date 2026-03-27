<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Paths;

/**
 * Konfigurace cest načtená z platformbridge.json.
 *
 * Sloučený value object — obsahuje validaci i přímé gettery na cesty.
 * Vytvářen přes {@see PathsLoader}.
 */
final class PathsConfig
{
    /** Název chráněného konfiguračního souboru (.json.php s PHP exit guardem) */
    public const CONFIG_FILE_PROTECTED = 'platformbridge.json.php';

    public const CONFIG_FILE = 'platformbridge.json';

    // ─── Konfigurační klíče (single source of truth pro názvy klíčů) ─

    public const KEY_ASSETS_PATH     = 'assets_path';
    public const KEY_BRIDGE_CONFIG   = 'bridge_config';
    public const KEY_SECURITY_CONFIG = 'security_config';
    public const KEY_CACHE_PATH      = 'cache_path';
    public const KEY_API_FILE        = 'api_file';

    // ─── Názvy stub souborů ──────────────────────────────────

    public const STUB_API      = 'api.php';
    public const STUB_BRIDGE   = 'bridge-config.php';
    public const STUB_SECURITY = 'security-config.php';

    private readonly array $config;

    /**
     * @param array<string, string> $defaults Výchozí hodnoty cest
     * @param array<string, mixed>  $data     Načtená data z JSON souboru
     */
    public function __construct(private readonly array $defaults, private readonly array $data)
    {
        $loaded = $this->validateData($data);
        $this->config = array_merge($this->defaults, array_intersect_key($loaded, $this->defaults));
    }

    // ─── Path getters ────────────────────────────────────────

    public function assets(): string
    {
        return $this->config[self::KEY_ASSETS_PATH];
    }

    public function cache(): string
    {
        return $this->config[self::KEY_CACHE_PATH];
    }

    public function api(): string
    {
        return $this->config[self::KEY_API_FILE];
    }

    public function security(): string
    {
        return $this->config[self::KEY_SECURITY_CONFIG];
    }

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

        // Validace: všechny hodnoty musí být stringy
        foreach ($loaded as $key => $value) {
            if (isset($this->defaults[$key]) && !is_string($value)) {
                throw new \RuntimeException(
                    $configBasename . ": key '{$key}' must be a string, got " . gettype($value)
                );
            }
        }

        // Bezpečnostní validace cest – prevence path traversal
        foreach ($loaded as $key => $value) {
            if (isset($this->defaults[$key]) && is_string($value)) {
                $this->validatePathSecurity($key, $value);
            }
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
        if (str_starts_with($normalized, '/') || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $value)) {
            throw new \RuntimeException(
                "Config: key '{$key}' must be a relative path, got absolute: '{$value}'. "
                . 'All paths are relative to the project root.'
            );
        }
    }
}
