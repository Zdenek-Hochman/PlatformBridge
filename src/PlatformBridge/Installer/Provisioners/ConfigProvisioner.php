<?php

declare(strict_types=1);

namespace PlatformBridge\Installer\Provisioners;

use PlatformBridge\Paths\PathsConfig;
use PlatformBridge\Paths\PathsLoader;
use PlatformBridge\Security\JsonGuard;

/**
 * Spravuje lifecycle konfiguračního souboru platformbridge.json(.php).
 *
 * Odpovědnosti:
 *   - Zjištění existence konfiguračního souboru (chráněný i nechráněný)
 *   - Migrace starého .json → .json.php
 *   - Generování výchozího konfiguračního souboru z {@see PathsLoader::defaults()}
 *
 * Konfigurační soubor se generuje přímo z PathsLoader::defaults() –
 * žádný stub soubor není potřeba. PathsLoader je single source of truth
 * pro výchozí cesty.
 *
 * Tato třída nepracuje s resolved cestami z PathsConfig – operuje
 * přímo se surovými soubory na disku (existence, čtení, konverze).
 *
 * Oddělena od Installeru, který slouží jako orchestrátor kroků.
 * Oddělena od PathsLoader, který předpokládá existující validní soubor.
 */
final class ConfigProvisioner
{
    /** Absolutní cesta k chráněnému konfiguračnímu souboru */
    private readonly string $protectedPath;

    /** Absolutní cesta k nechráněnému (starému) konfiguračnímu souboru */
    private readonly string $plainPath;

    /**
     * @param string $projectRoot Kořen hostující aplikace
     */
    public function __construct(string $projectRoot)
    {
        $root = rtrim($projectRoot, '/\\');

        $this->protectedPath = $root . DIRECTORY_SEPARATOR . PathsConfig::CONFIG_FILE_PROTECTED;
        $this->plainPath     = $root . DIRECTORY_SEPARATOR . PathsConfig::CONFIG_FILE;
    }

    // ─── Dotazy na stav ──────────────────────────────────────

    /**
     * Existuje chráněný konfigurační soubor (.json.php)?
     */
    private function protectedExists(): bool
    {
        return file_exists($this->protectedPath);
    }

    /**
     * Existuje starý nechráněný konfigurační soubor (.json)?
     */
    private function plainExists(): bool
    {
        return file_exists($this->plainPath);
    }

    /**
     * Je potřeba migrace? (existuje starý .json, ale chybí .json.php)
     */
    private function needsMigration(): bool
    {
        return !$this->protectedExists() && $this->plainExists();
    }

    // ─── Akce ────────────────────────────────────────────────

    /**
     * Zmigruje starý nechráněný .json na chráněný .json.php.
     *
     * Starý soubor se po konverzi smaže.
     *
     * @return bool True pokud migrace proběhla
     * @throws \RuntimeException Pokud zdrojový soubor nelze přečíst
     */
    public function migrate(): bool
    {
        return JsonGuard::convertToProtected(
            $this->plainPath,
            $this->protectedPath,
            deleteSource: true,
        );
    }

    /**
     * Vygeneruje výchozí konfigurační soubor z PathsLoader::defaults().
     *
     * Soubor se vytváří POUZE pokud chráněný config ještě neexistuje.
     * Toto je uživatelem spravovaný soubor – nikdy se nepřepisuje.
     * Výchozí cesty pochází z PathsLoader::defaults(true) (vendor režim).
     */
    private function publishDefaults(): void
    {
        if ($this->protectedExists()) {
            return;
        }

        $json = PathsLoader::defaultsAsJson(true);
        JsonGuard::writeProtected($this->protectedPath, $json);
    }

    /**
     * Kompletní provisioning: migrace → publish → vrátí výsledek.
     *
     * Pořadí operací:
     *   1. Pokud existuje starý .json bez .json.php → migrace
     *   2. Pokud neexistuje .json.php → vygeneruj z výchozí konfigurace
     *   3. Pokud .json.php existuje → skip
     *
     * @return ProvisionResult Výsledek operace s kontextem pro výpis
     */
    public function provision(): ProvisionResult
    {
        // 1. Migrace starého formátu
        if ($this->needsMigration()) {
            $this->migrate();
            return ProvisionResult::Migrated;
        }

        // 2. Generuj výchozí config (nová instalace)
        if (!$this->protectedExists()) {
            $this->publishDefaults();
            return ProvisionResult::Published;
        }

        // 3. Soubor už existuje
        return ProvisionResult::Skipped;
    }

    // ─── Introspekce ─────────────────────────────────────────

    /**
     * Absolutní cesta k chráněnému konfiguračnímu souboru.
     */
    public function protectedPath(): string
    {
        return $this->protectedPath;
    }

    /**
     * Absolutní cesta k nechráněnému (starému) konfiguračnímu souboru.
     */
    public function plainPath(): string
    {
        return $this->plainPath;
    }
}
