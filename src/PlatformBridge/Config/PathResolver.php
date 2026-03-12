<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config;

/**
 * Centrální resoluce cest – eliminuje pevné cesty v celém balíčku.
 *
 * Podporuje:
 *   - vendor režim: balíček v vendor/zoom/platform-bridge/
 *   - standalone režim: balíček jako root projekt (XAMPP dev)
 */
final class PathResolver
{
    private readonly string $packageRoot;
    private readonly string $projectRoot;
    private readonly bool $isVendor;

    public function __construct(?string $packageRoot = null)
    {
        $this->packageRoot = $packageRoot ?? dirname(__DIR__, 3);
        $this->isVendor = $this->detectVendorMode();
        $this->projectRoot = $this->isVendor
            ? dirname($this->packageRoot, 3)
            : $this->packageRoot;
    }

    private function detectVendorMode(): bool
    {
        $autoload = dirname($this->packageRoot, 2) . DIRECTORY_SEPARATOR . 'autoload.php';
        return file_exists($autoload)
            && realpath($this->packageRoot) !== realpath(dirname($this->packageRoot, 3));
    }

    // ─── Package paths (uvnitř vendor) ──────────────────────────

    /** Kořen balíčku */
    public function packageRoot(): string
    {
        return $this->packageRoot;
    }

    /** Výchozí referenční konfigurace balíčku */
    public function packageConfigPath(): string
    {
        return $this->packageRoot . '/config';
    }

    /** Výchozí JSON defaults */
    public function packageDefaultsPath(): string
    {
        return $this->packageRoot . '/resources/defaults';
    }

    /** Dist assety (JS/CSS) */
    public function packageDistPath(): string
    {
        return $this->packageRoot . '/dist';
    }

    /** Views šablony */
    public function packageViewsPath(): string
    {
        return $this->packageRoot . '/resources/views';
    }

    /** Stubs pro publish */
    public function packageStubsPath(): string
    {
        return $this->packageRoot . '/resources/stubs';
    }

    // ─── Project paths (v hostující aplikaci) ───────────────────

    /** Kořen hostující aplikace */
    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    /** Uživatelská konfigurace */
    public function userConfigPath(): string
    {
        return $this->projectRoot . '/config/platform-bridge';
    }

    /** Uživatelský bridge-config.php */
    public function userBridgeConfigFile(): string
    {
        return $this->userConfigPath() . '/bridge-config.php';
    }

    /** Cesta k uživatelským JSON souborům */
    public function userJsonPath(): string
    {
        return $this->userConfigPath();
    }

    /** Public assets */
    public function publicAssetsPath(): string
    {
        return $this->projectRoot . '/public/platformbridge';
    }

    /** Cache adresář */
    public function cachePath(): string
    {
        return $this->projectRoot . '/var/cache';
    }

    // ─── Resolved paths (user → package fallback) ──────────────

    /**
     * Vrátí cestu ke konfiguraci s fallbackem.
     * Priorita: user config → package defaults
     */
    public function resolvedConfigPath(): string
    {
        $userPath = $this->userConfigPath();
        if ($this->isVendor && is_dir($userPath) && $this->hasJsonFiles($userPath)) {
            return $userPath;
        }
        return $this->packageDefaultsPath();
    }

    /**
     * Vrátí cestu k bridge-config.php s fallbackem.
     * Priorita: user config → package config
     */
    public function resolvedBridgeConfigFile(): string
    {
        $userFile = $this->userBridgeConfigFile();
        if (file_exists($userFile)) {
            return $userFile;
        }
        return $this->packageConfigPath() . '/bridge-config.php';
    }

    public function isVendor(): bool
    {
        return $this->isVendor;
    }

    private function hasJsonFiles(string $dir): bool
    {
        return glob($dir . '/*.json') !== [];
    }
}
