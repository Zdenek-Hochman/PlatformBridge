<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config;

use Zoom\PlatformBridge\Security\JsonGuard;

/**
 * Načítá uživatelskou konfiguraci instalačních cest z platformbridge.json.php
 * (nebo zpětně kompatibilního platformbridge.json) v kořeni hostitelské aplikace.
 *
 * Pokud soubor neexistuje, vrací výchozí hodnoty (zpětná kompatibilita).
 * Tím je zajištěno, že installer i runtime PathResolver fungují:
 *   - bez konfigurace → výchozí cesty (public/platformbridge, config/…)
 *   - s platformbridge.json.php → uživatelem definované cesty
 *
 * Formát souboru (JSON data uvnitř PHP exit guardu):
 *
 *   <?php header('HTTP/1.1 403 Forbidden'); exit; ?>
 *   {
 *       "assets_path":     "public/platformbridge",
 *       "bridge_config":   "public/bridge-config.php",
 *       "security_config": "config/security-config.php",
 *       "cache_path":      "var/cache",
 *       "api_file":        "public/platformbridge/api.php",
 *       "json_path":       "config/platform-bridge"
 *   }
 *
 * Klíče, které v souboru chybí, se doplní výchozími hodnotami.
 *
 * Bezpečnost:
 *   JSON formát zajišťuje, že konfigurační soubor nemůže obsahovat spustitelný kód.
 *   Na rozdíl od PHP souboru (require) nelze přes JSON injektovat backdoor ani
 *   žádný executable payload. Soubor je čten přes file_get_contents + json_decode.
 *   Cesty jsou navíc validovány proti path traversal útokům.
 *
 *   PHP exit guard v .json.php souboru zajišťuje, že při přístupu přes webový
 *   prohlížeč se zobrazí HTTP 403 Forbidden – JSON obsah zůstane skrytý.
 *   Toto funguje na jakémkoli PHP hostingu bez nutnosti .htaccess nebo
 *   konfigurace serveru. Viz {@see JsonGuard}.
 *
 * Priorita načítání:
 *   1. platformbridge.json.php (chráněný formát – preferovaný)
 *   2. platformbridge.json     (nechráněný – zpětná kompatibilita, s varováním)
 *   3. platformbridge.php      (starý PHP formát – chyba s pokyny k migraci)
 */
final class InstallerConfig
{
    /** Název chráněného konfiguračního souboru (.json.php s PHP exit guardem) */
    public const CONFIG_FILE_PROTECTED = 'platformbridge.json.php';

    /**
     * Název nechráněného konfiguračního souboru (zpětná kompatibilita).
     * Nové instalace vždy vytvoří CONFIG_FILE_PROTECTED.
     */
    public const CONFIG_FILE = 'platformbridge.json';

    /**
     * @deprecated Ponecháno pro detekci starého PHP formátu (migrace).
     */
    private const LEGACY_CONFIG_FILE = 'platformbridge.php';

    /** Povolené klíče v konfiguračním souboru */
    private const ALLOWED_KEYS = [
        'assets_path',
        'bridge_config',
        'security_config',
        'cache_path',
        'api_file',
        'json_path',
    ];

    /** @var array<string, string> Výchozí relativní cesty */
    private const DEFAULTS = [
        'assets_path'     => 'public/platformbridge',
        'bridge_config'   => 'public/bridge-config.php',
        'security_config' => 'config/security-config.php',
        'cache_path'      => 'var/cache',
        'api_file'        => 'public/platformbridge/api.php',
        'json_path'       => 'config/platform-bridge',
    ];

    /** @var array<string, string> Zvalidované relativní cesty */
    private readonly array $config;

    /** Indikuje, zda byl nalezen a načten uživatelský konfigurační soubor */
    private readonly bool $hasCustomConfig;

    /** Indikuje, zda je konfigurace uložena v chráněném formátu (.json.php) */
    private readonly bool $isProtected;

    /**
     * @param string $projectRoot Absolutní cesta ke kořeni hostitelské aplikace
     */
    public function __construct(string $projectRoot)
    {
        $root = rtrim($projectRoot, '/\\');

        // Cesty ke konfiguračním souborům (v pořadí priority)
        $protectedFile = $root . DIRECTORY_SEPARATOR . self::CONFIG_FILE_PROTECTED;
        $plainFile = $root . DIRECTORY_SEPARATOR . self::CONFIG_FILE;
        $legacyFile = $root . DIRECTORY_SEPARATOR . self::LEGACY_CONFIG_FILE;

        // Detekce starého PHP formátu → srozumitelná chybová hláška pro migraci
        if (!file_exists($protectedFile) && !file_exists($plainFile) && file_exists($legacyFile)) {
            throw new \RuntimeException(
                'Legacy PHP config file detected: ' . self::LEGACY_CONFIG_FILE . '. '
                . 'PlatformBridge now uses JSON format for security. '
                . 'Run "php vendor/bin/platformbridge init" to generate ' . self::CONFIG_FILE_PROTECTED . ', '
                . 'then transfer your settings and delete ' . self::LEGACY_CONFIG_FILE . '.'
            );
        }

        if (file_exists($protectedFile)) {
            // Chráněný formát (.json.php) – preferovaný
            $loaded = $this->loadAndValidateJson($protectedFile);
            $this->config = array_merge(self::DEFAULTS, array_intersect_key($loaded, self::DEFAULTS));
            $this->hasCustomConfig = true;
            $this->isProtected = true;
        } elseif (file_exists($plainFile)) {
            // Nechráněný formát (.json) – zpětná kompatibilita
            $loaded = $this->loadAndValidateJson($plainFile);
            $this->config = array_merge(self::DEFAULTS, array_intersect_key($loaded, self::DEFAULTS));
            $this->hasCustomConfig = true;
            $this->isProtected = false;

            // Varování: nechráněný soubor je přístupný přes web
            trigger_error(
                'PlatformBridge: ' . self::CONFIG_FILE . ' is not protected against web access. '
                . 'Run "php vendor/bin/platformbridge install --only=init" to upgrade '
                . 'to secured ' . self::CONFIG_FILE_PROTECTED . ' format.',
                E_USER_NOTICE
            );
        } else {
            $this->config = self::DEFAULTS;
            $this->hasCustomConfig = false;
            $this->isProtected = false;
        }
    }

    /**
     * Bezpečně načte a zvaliduje JSON konfigurační soubor.
     *
     * Validace:
     *   1. Soubor musí být čitelný a neprázdný
     *   2. Obsah musí být validní JSON objekt
     *   3. Maximální velikost 64 KB (prevence DoS)
     *   4. Všechny hodnoty musí být stringy
     *   5. Neznámé klíče → varování (tolerantní, ale informuje)
     *   6. Cesty jsou validovány proti path traversal (../, absolutní cesty)
     *
     * @return array<string, string> Validované konfigurační pole
     * @throws \RuntimeException Pokud soubor nelze načíst nebo je nevalidní
     */
    private function loadAndValidateJson(string $configFile): array
    {
        // Bezpečnostní limit na velikost souboru (64 KB by mělo stačit i pro budoucí klíče)
        $maxSize = 65536;
        $fileSize = filesize($configFile);
        if ($fileSize === false || $fileSize > $maxSize) {
            throw new \RuntimeException(
                basename($configFile) . ' is too large (max ' . ($maxSize / 1024) . ' KB). '
                . 'This may indicate file corruption or a security issue.'
            );
        }

        $content = file_get_contents($configFile);
        if ($content === false || trim($content) === '') {
            throw new \RuntimeException(
                basename($configFile) . ' is empty or unreadable.'
            );
        }

        // Odstraň PHP exit guard pokud je přítomen (.json.php formát)
        $content = JsonGuard::strip($content);

        $loaded = json_decode($content, true, 4, JSON_THROW_ON_ERROR);

        if (!is_array($loaded)) {
            throw new \RuntimeException(
                basename($configFile) . ' must contain a JSON object, got ' . gettype($loaded)
            );
        }

        // Filtrovat meta klíče ($schema, $comment) – nejsou konfigurační
        $loaded = array_filter($loaded, static fn (string $key) => !str_starts_with($key, '$'), ARRAY_FILTER_USE_KEY);

        $configBasename = basename($configFile);

        // Neznámé klíče → varování
        $unknown = array_diff_key($loaded, self::DEFAULTS);
        if ($unknown !== []) {
            trigger_error(
                $configBasename . ': unknown keys ignored: ' . implode(', ', array_keys($unknown)),
                E_USER_NOTICE
            );
        }

        // Validace: všechny hodnoty musí být stringy
        foreach ($loaded as $key => $value) {
            if (isset(self::DEFAULTS[$key]) && !is_string($value)) {
                throw new \RuntimeException(
                    $configBasename . ": key '{$key}' must be a string, got " . gettype($value)
                );
            }
        }

        // Bezpečnostní validace cest – prevence path traversal
        foreach ($loaded as $key => $value) {
            if (isset(self::DEFAULTS[$key]) && is_string($value)) {
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
        // Null byte injection
        if (str_contains($value, "\0")) {
            throw new \RuntimeException(
                "Config: key '{$key}' contains null byte – possible injection attack."
            );
        }

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

    // ─── Gettery pro jednotlivé cesty ────────────────────────

    /** Relativní cesta ke složce s publikovanými assety (JS/CSS) */
    public function assetsPath(): string
    {
        return $this->config['assets_path'];
    }

    /** Relativní cesta k bridge-config.php */
    public function bridgeConfig(): string
    {
        return $this->config['bridge_config'];
    }

    /** Relativní cesta k security-config.php */
    public function securityConfig(): string
    {
        return $this->config['security_config'];
    }

    /** Relativní cesta ke cache složce */
    public function cachePath(): string
    {
        return $this->config['cache_path'];
    }

    /** Relativní cesta k API souboru */
    public function apiFile(): string
    {
        return $this->config['api_file'];
    }

    /** Relativní cesta ke složce s JSON konfigurací (blocks.json, layouts.json, …) */
    public function jsonPath(): string
    {
        return $this->normalizeRelativePath($this->config['json_path']);
    }

    // ─── Meta ────────────────────────────────────────────────

    /** Zda byl nalezen uživatelský konfigurační soubor */
    public function hasCustomConfig(): bool
    {
        return $this->hasCustomConfig;
    }

    /** Zda je konfigurace v chráněném .json.php formátu */
    public function isProtected(): bool
    {
        return $this->isProtected;
    }

    /** Název preferovaného konfiguračního souboru (chráněný) */
    public static function configFileName(): string
    {
        return self::CONFIG_FILE_PROTECTED;
    }

    /** Název nechráněného konfiguračního souboru (legacy) */
    public static function plainConfigFileName(): string
    {
        return self::CONFIG_FILE;
    }

    /** Výchozí hodnoty všech klíčů */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /**
     * Normalizuje relativní cestu – odstraní úvodní './' a trailing lomítka.
     */
    private function normalizeRelativePath(string $path): string
    {
        $path = rtrim($path, '/\\');
        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        return $path;
    }

    /** Vrátí celé konfigurační pole */
    public function toArray(): array
    {
        return $this->config;
    }
}
