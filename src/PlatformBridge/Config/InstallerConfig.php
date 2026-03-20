<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config;

/**
 * Načítá uživatelskou konfiguraci instalačních cest z platformbridge.php
 * v kořeni hostitelské aplikace.
 *
 * Pokud soubor neexistuje, vrací výchozí hodnoty (zpětná kompatibilita).
 * Tím je zajištěno, že installer i runtime PathResolver fungují:
 *   - bez konfigurace → výchozí cesty (public/platformbridge, config/…)
 *   - s platformbridge.php → uživatelem definované cesty
 *
 * Soubor platformbridge.php vrací pole s relativními cestami od kořene projektu:
 *
 *   return [
 *       'assets_path'     => 'public/platformbridge',
 *       'bridge_config'   => 'public/bridge-config.php',
 *       'security_config' => 'config/security-config.php',
 *       'json_path'       => 'config/platform-bridge',
 *       'cache_path'      => 'var/cache',
 *       'api_file'        => 'public/platformbridge/api.php',
 *   ];
 *
 * Klíče, které v souboru chybí, se doplní výchozími hodnotami.
 */
final class InstallerConfig
{
    /** Název konfiguračního souboru hledaného v project root */
    public const CONFIG_FILE = 'platformbridge.php';

    /** @var array<string, string> Výchozí relativní cesty */
    private const DEFAULTS = [
        'assets_path'     => 'public/platformbridge',
        'bridge_config'   => 'public/bridge-config.php',
        'security_config' => 'config/security-config.php',
        'json_path'       => 'config/platform-bridge',
        'cache_path'      => 'var/cache',
        'api_file'        => 'public/platformbridge/api.php',
    ];

    /** @var array<string, string> Zvalidované relativní cesty */
    private readonly array $config;

    /** Indikuje, zda byl nalezen a načten uživatelský konfigurační soubor */
    private readonly bool $hasCustomConfig;

    /**
     * @param string $projectRoot Absolutní cesta ke kořeni hostitelské aplikace
     */
    public function __construct(string $projectRoot)
    {
        $configFile = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . self::CONFIG_FILE;

        if (file_exists($configFile)) {
            $loaded = require $configFile;

            if (!is_array($loaded)) {
                throw new \RuntimeException(
                    self::CONFIG_FILE . ' must return an array, got ' . gettype($loaded)
                );
            }

            // Neznámé klíče → varování
            $unknown = array_diff_key($loaded, self::DEFAULTS);
            if ($unknown !== []) {
                trigger_error(
                    self::CONFIG_FILE . ': unknown keys ignored: ' . implode(', ', array_keys($unknown)),
                    E_USER_NOTICE
                );
            }

            // Validace: všechny hodnoty musí být stringy
            foreach ($loaded as $key => $value) {
                if (isset(self::DEFAULTS[$key]) && !is_string($value)) {
                    throw new \RuntimeException(
                        self::CONFIG_FILE . ": key '{$key}' must be a string, got " . gettype($value)
                    );
                }
            }

            $this->config = array_merge(self::DEFAULTS, array_intersect_key($loaded, self::DEFAULTS));
            $this->hasCustomConfig = true;
        } else {
            $this->config = self::DEFAULTS;
            $this->hasCustomConfig = false;
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

    /** Relativní cesta ke složce s JSON konfigurací */
    public function jsonPath(): string
    {
        return $this->config['json_path'];
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

    // ─── Meta ────────────────────────────────────────────────

    /** Zda byl nalezen uživatelský platformbridge.php */
    public function hasCustomConfig(): bool
    {
        return $this->hasCustomConfig;
    }

    /** Název konfiguračního souboru */
    public static function configFileName(): string
    {
        return self::CONFIG_FILE;
    }

    /** Výchozí hodnoty všech klíčů */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /** Vrátí celé konfigurační pole */
    public function toArray(): array
    {
        return $this->config;
    }
}
