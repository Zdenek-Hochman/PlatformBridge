<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Installer\Provisioners;

use Zoom\PlatformBridge\Paths\PathsConfig;
use Zoom\PlatformBridge\Installer\Publisher\PublishResult;
use Zoom\PlatformBridge\Installer\Publisher\StubPublisher;


/**
 * Publikuje jednotlivé stub soubory do hostující aplikace.
 *
 * Obsluhuje kroky: api, config, security.
 * Všechny tři sdílejí identický vzor: stub → cíl, skip pokud existuje,
 * přepis pokud --force.
 *
 * Oddělena od Installeru, který slouží jako orchestrátor kroků.
 *
 * @see ConfigProvisioner Pro lifecycle konfiguračního souboru platformbridge.json.php
 * @see PublishResult     Pro strukturovaný výstup operace
 */
final class FileProvisioner
{
    /** @var array<string, array{stub: string, target: string, label: string, replacements?: array<string,string>}> */
    private readonly array $files;

    /**
     * @param string      $stubsPath   Absolutní cesta ke stubs adresáři balíčku
     * @param string      $projectRoot Absolutní cesta ke kořeni hostující aplikace
     * @param PathsConfig $config      Konfigurace cest (relativní cílové cesty)
     */
    public function __construct(string $stubsPath, string $projectRoot, PathsConfig $config)
    {
        $stubs = rtrim($stubsPath, '/\\');
        $root = rtrim($projectRoot, '/\\');

        $this->files = [
            'api' => [
                'stub'         => $stubs . DIRECTORY_SEPARATOR . PathsConfig::STUB_API,
                'target'       => $root . DIRECTORY_SEPARATOR . $config->api(),
                'label'        => $config->api(),
                'replacements' => [
                    '"{{AUTOLOAD_PATH}}"' => self::buildAutoloadExpression($config->api()),
                ],
            ],
            'config' => [
                'stub'   => $stubs . DIRECTORY_SEPARATOR . PathsConfig::STUB_BRIDGE,
                'target' => $root . DIRECTORY_SEPARATOR . $config->bridge(),
                'label'  => $config->bridge(),
            ],
            'security' => [
                'stub'   => $stubs . DIRECTORY_SEPARATOR . PathsConfig::STUB_SECURITY,
                'target' => $root . DIRECTORY_SEPARATOR . $config->security(),
                'label'  => $config->security(),
            ],
        ];
    }

    // ─── Akce ────────────────────────────────────────────────

    /**
     * Publikuje soubor podle názvu kroku.
     *
     * Bez force se existující soubor nepřepisuje (uživatel ho mohl upravit).
     * S force se soubor přepíše stubem.
     *
     * @param string        $name      Název kroku: 'api', 'config', 'security'
     * @param StubPublisher $publisher Publisher pro bezpečný zápis
     * @param bool          $force     Přepsat existující soubor
     * @return PublishResult Výsledek operace
     * @throws \InvalidArgumentException Neznámý název kroku
     */
    public function provision(string $name, StubPublisher $publisher, bool $force = false): PublishResult
    {
        $file = $this->resolve($name);

        $replacements = $file['replacements'] ?? [];

        $written = $replacements !== []
            ? $publisher->publishWithReplacements($file['stub'], $file['target'], $replacements, overwrite: $force)
            : $publisher->publish($file['stub'], $file['target'], overwrite: $force);

        return $written
            ? PublishResult::published($file['label'])
            : PublishResult::skipped($file['label']);
    }

    // ─── Dotazy na stav ──────────────────────────────────────

    /**
     * Zda cílový soubor existuje.
     */
    public function exists(string $name): bool
    {
        return file_exists($this->resolve($name)['target']);
    }

    /**
     * Zda zdrojový stub soubor existuje.
     */
    public function stubExists(string $name): bool
    {
        return file_exists($this->resolve($name)['stub']);
    }

    /**
     * Vrátí podporované názvy kroků.
     *
     * @return list<string>
     */
    public function supportedSteps(): array
    {
        return array_keys($this->files);
    }

    // ─── Internals ───────────────────────────────────────────

    /**
     * @return array{stub: string, target: string, label: string, replacements?: array<string,string>}
     * @throws \InvalidArgumentException
     */
    private function resolve(string $name): array
    {
        return $this->files[$name]
            ?? throw new \InvalidArgumentException(
                "Unknown file step: '{$name}'. Allowed: " . implode(', ', array_keys($this->files))
            );
    }

    // ─── Internals ───────────────────────────────────────────

    /**
     * Sestaví PHP výraz pro cestu k vendor/autoload.php z relativní cesty k API souboru.
     *
     * Hloubka = počet adresářových úrovní v relativní cestě.
     * Příklady:
     *   "public/api.php"                     → dirname(__DIR__) . '/vendor/autoload.php'
     *   "public/platformbridge/api.php"       → dirname(__DIR__, 2) . '/vendor/autoload.php'
     *   "web/app/bridge/api.php"              → dirname(__DIR__, 3) . '/vendor/autoload.php'
     */
    private static function buildAutoloadExpression(string $apiRelativePath): string
    {
        // Normalizuj separátory a spočítej adresářové segmenty
        $normalized = str_replace('\\', '/', $apiRelativePath);
        $depth = substr_count(dirname($normalized), '/');

        // depth 0 = soubor přímo v rootu (api.php) → dirname(__DIR__, 0) není validní,
        // ale reálně se to nestane – api je vždy alespoň v public/
        $depthArg = $depth + 1;

        $dirExpr = $depthArg === 1
            ? "dirname(__DIR__)"
            : "dirname(__DIR__, {$depthArg})";

        return "{$dirExpr} . '/vendor/autoload.php'";
    }
}
