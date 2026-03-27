<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Installer\Provisioners;

use Zoom\PlatformBridge\Paths\PathsConfig;
use Zoom\PlatformBridge\Security\JsonGuard;
use Zoom\PlatformBridge\Installer\Publisher\PublishResult;

/**
 * Spravuje adresářovou strukturu hostující aplikace.
 *
 * Odpovědnosti:
 *   - Vytvoření adresářové struktury dle konfigurace (dirs step)
 *   - Publikování ochranných index.php guardů (guard step)
 *   - Zajištění cache adresáře (cache step)
 *
 * Oddělena od Installeru pro jasné oddělení zodpovědností.
 * Všechny cesty čerpá z {@see PathsConfig}.
 *
 * @see JsonGuard::createDirectoryGuard() Pro vytvoření sentinel souborů
 * @see PublishResult                     Pro strukturovaný výstup
 */
final class DirectoryProvisioner
{
    private readonly string $projectRoot;

    /**
     * @param string      $projectRoot Absolutní cesta ke kořeni hostující aplikace
     * @param PathsConfig $config      Konfigurace cest
     */
    public function __construct(string $projectRoot, private readonly PathsConfig $config)
    {
        $this->projectRoot = rtrim($projectRoot, '/\\');
    }

    // ─── Directory structure (dirs step) ─────────────────────

    /**
     * Vytvoří kompletní adresářovou strukturu podle konfigurace.
     *
     * Zajistí existenci adresářů pro všechny konfigurované cesty
     * PŘED publikováním souborů. Tím se řeší situace, kdy:
     *   - Uživatel má vlastní platformbridge.json s nestandardními cestami
     *   - Některé publish kroky jsou přeskočeny (soubor existuje)
     *   - Aplikace potřebuje adresáře ještě před prvním installem
     *
     * @return list<PublishResult> Výsledek pro každý vytvořený adresář
     */
    public function ensureStructure(): array
    {
        $dirs = $this->collectDirectories();
        $results = [];
        $created = 0;

        foreach ($dirs as $relDir => $label) {
            if ($relDir === '.' || $relDir === '') {
                continue;
            }

            $absDir = $this->projectRoot . DIRECTORY_SEPARATOR . $relDir;

            if (!is_dir($absDir)) {
                mkdir($absDir, 0755, true);
                $results[] = PublishResult::created("{$relDir} ({$label})");
                $created++;
            } else {
				$results[] = PublishResult::exists("{$relDir}/ (exists)");
			}
        }

		if ($created === 0) {
            $results[] = PublishResult::exists('Directory structure: OK (all directories exist)');
        }

        return $results;
    }

    // ─── Directory guards (guard step) ───────────────────────

    /**
     * Publikuje ochranné index.php soubory v adresářích s citlivými daty.
     *
     * Tyto sentinel soubory:
     *   - Brání directory listing (výpis obsahu adresáře)
     *   - Vrací HTTP 403 Forbidden při přímém přístupu na URL adresáře
     *   - Fungují na jakémkoli PHP hostingu bez konfigurace serveru
     *
     * @return list<PublishResult> Výsledek pro každý guard
     */
    public function ensureGuards(): array
    {
        $protectedDirs = $this->collectGuardDirectories();

        $results = [];
        $guardCount = 0;

        foreach ($protectedDirs as $relDir => $label) {
            $absDir = $this->projectRoot . DIRECTORY_SEPARATOR . $relDir;

            if (is_dir($absDir)) {
                $created = JsonGuard::createDirectoryGuard($absDir);
                if ($created) {
                    $results[] = PublishResult::guarded("{$relDir}/index.php ({$label})");
                    $guardCount++;
                }
            }
        }

        if ($guardCount === 0) {
            $results[] = PublishResult::exists('Directory guards: OK (all guards in place)');
        }

        return $results;
    }

    // ─── Cache directory (cache step) ────────────────────────

    /**
     * Zajistí existenci cache adresáře.
     *
     * Cache adresář je nutný pro šablonovou cache a musí existovat
     * i pokud ostatní adresáře už byly vytvořeny v dirs kroku.
     */
    public function ensureCache(): PublishResult
    {
        $relPath = $this->config->cache();
        $absPath = $this->projectRoot . DIRECTORY_SEPARATOR . $relPath;

        if (!is_dir($absPath)) {
            mkdir($absPath, 0755, true);
            return PublishResult::created($relPath);
        }

        return PublishResult::exists($relPath . '/ (exists)');
    }

    // ─── Internals ───────────────────────────────────────────

    /**
     * Sbírka adresářů potřebných pro instalaci.
     *
     * @return array<string, string> Relativní cesta → popis
     */
    private function collectDirectories(): array
    {
        return [
            $this->config->assets()                    => 'assets (JS/CSS)',
            dirname($this->config->bridge())           => 'bridge config',
            dirname($this->config->security())         => 'security config',
            $this->config->cache()                     => 'cache',
            dirname($this->config->api())              => 'API endpoint',
        ];
    }

    /**
     * Adresáře vyžadující ochranný index.php guard.
     *
     * Chráněné adresáře:
     *   - cache_path (šablonová cache)
     *   - adresář s konfigurací (security-config.php nadřazený adresář)
     *
     * @return array<string, string> Relativní cesta → popis
     */
    private function collectGuardDirectories(): array
    {
        $dirs = [
            $this->config->cache() => 'template cache',
        ];

        // Nadřazený adresář security configu (pokud není root)
        $securityDir = dirname($this->config->security());

        if ($securityDir !== '.' && $securityDir !== '') {
            $dirs[$securityDir] = 'security config';
        }

        return $dirs;
    }
}
