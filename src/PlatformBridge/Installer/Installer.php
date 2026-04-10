<?php

declare(strict_types=1);

namespace PlatformBridge\Installer;

use PlatformBridge\Paths\PathResolverFactory;
use PlatformBridge\Paths\PathResolver;
use PlatformBridge\Paths\PathsConfig;

use PlatformBridge\Installer\Publisher\StubPublisher;
use PlatformBridge\Installer\Provisioners\ConfigProvisioner;
use PlatformBridge\Installer\Provisioners\ProvisionResult;
use PlatformBridge\Installer\Provisioners\FileProvisioner;
use PlatformBridge\Installer\Provisioners\AssetProvisioner;
use PlatformBridge\Installer\Provisioners\DirectoryProvisioner;
// use PlatformBridge\Translator\Database\TableProvisioner;

/**
 * Orchestrátor instalačních kroků PlatformBridge.
 *
 * Installer sám neobsahuje žádnou publish/guard/directory logiku –
 * deleguje ji na specializované provisioner třídy:
 *
 *   - {@see ConfigProvisioner}    → init step (platformbridge.json.php lifecycle)
 *   - {@see DirectoryProvisioner} → dirs, guard, cache steps
 *   - {@see AssetProvisioner}     → assets step
 *   - {@see FileProvisioner}      → api, config, security steps
 *
 * Každý provisioner vrací typované výsledky ({@see ProvisionResult}, {@see PublishResult}),
 * Installer je pouze formátuje do CLI výstupu.
 *
 * Kroky: init → dirs → guard → assets → api → config → security → cache
 */
final class Installer
{
    private PathResolver $paths;

    // ─── Provisioners ────────────────────────────────────────

    private StubPublisher $publisher;
    private ConfigProvisioner $configProvisioner;
    private FileProvisioner $fileProvisioner;
    private AssetProvisioner $assetProvisioner;
    private DirectoryProvisioner $directoryProvisioner;

    // ─── Options ─────────────────────────────────────────────

    /** Přepsat i existující konfigurační soubory */
    private bool $force = false;

    /** Pokud neprázdné, spustí jen vybrané kroky */
    private array $only = [];

    /** @var list<string> Povolené názvy kroků pro --only */
    private const ALLOWED_STEPS = ['init', 'dirs', 'assets', 'api', 'config', 'security', 'translations', 'cache', 'guard'];

    public function __construct(?string $packageRoot = null)
    {
        $this->paths = PathResolverFactory::auto($packageRoot ?? self::detectPackageRoot());
        $this->publisher = new StubPublisher();
        $this->buildProvisioners();
    }

    // ─── CLI options ─────────────────────────────────────────

    /**
     * Nastaví --force (přepíše i existující konfigurační soubory).
     */
    private function setForce(bool $force): self
    {
        $this->force = $force;
        return $this;
    }

    /**
     * Nastaví --only filtr (spustí jen vybrané kroky).
     *
     * @param list<string> $steps Názvy kroků
     * @throws \InvalidArgumentException Pokud obsahuje neplatný krok
     */
    private function setOnly(array $steps): self
    {
        $invalid = array_diff($steps, self::ALLOWED_STEPS);
        if ($invalid !== []) {
            throw new \InvalidArgumentException(
                'Unknown install steps: ' . implode(', ', $invalid)
                . '. Allowed: ' . implode(', ', self::ALLOWED_STEPS)
            );
        }

        $this->only = $steps;
        return $this;
    }

    /**
     * Parsuje CLI argumenty z $argv a nastaví příslušné options.
     *
     * @param list<string> $argv Argumenty příkazové řádky
     */
    public function applyCliOptions(array $argv): self
    {
        foreach ($argv as $arg) {
            if ($arg === '--force') {
                $this->setForce(true);
                continue;
            }

            if (str_starts_with($arg, '--only=')) {
                $value = substr($arg, strlen('--only='));
                $steps = array_filter(array_map('trim', explode(',', $value)));
                $this->setOnly($steps);
                continue;
            }
        }

        return $this;
    }

    // ─── Install ─────────────────────────────────────────────

    /**
     * Kompletní instalace – konfigurace, adresáře, assety, soubory.
     *
     * Kroky: init → dirs → guard → assets → api → config → security → cache
     */
    public function install(): void
    {
        $this->info("PlatformBridge Installer");
        $this->info("========================");

        if ($this->force) {
            $this->info("Flag: --force (overwrite configs)");
        }

        if ($this->only !== []) {
            $this->info("Flag: --only=" . implode(',', $this->only));
        }

        $this->info("");

        // 1. Zajisti konfigurační soubor (migrace / publish / skip)
        $this->runStep('init', $this->stepInit(...));

        // 2. Vytvoř adresářovou strukturu
        $this->runStep('dirs', $this->stepDirs(...));

        // 3. Publikuj ochranné guardy
        $this->runStep('guard', $this->stepGuards(...));

        // 4. Publikuj assety
        $this->runStep('assets', $this->stepAssets(...));

        // 5. Publikuj API endpoint
        $this->runStep('api', $this->stepFile('api'));

        // 6. Publikuj bridge config
        $this->runStep('config', $this->stepFile('config'));

        // 7. Publikuj security config
        $this->runStep('security', $this->stepFile('security'));

        // 8. Zajisti tabulku pro překlady (pokud je mysqli k dispozici)
        $this->runStep('translations', $this->stepTranslations(...));

        // 9. Zajisti cache adresář
        $this->runStep('cache', $this->stepCache(...));

        $this->info("\n✅ PlatformBridge installed successfully!");
    }

	public function update(): void {
		$this->info("PlatformBridge Updater");
        $this->info("========================");
        $this->info("");

        // 1. Publikuj assety
        $this->runStep('assets', $this->stepAssets(...));

        // 2. Publikuj API endpoint
        $this->runStep('api', $this->stepFile('api'));
	}

	public function init() {
		$this->info("PlatformBridge Init");
        $this->info("========================");
        $this->info("");

        $this->runStep('init', $this->stepInit(...));
	}

    // ─── Step implementations ────────────────────────────────
    //
    // Každá metoda pouze deleguje na provisioner a vypisuje výsledek.
    // Žádná publish/guard/directory logika zde nepatří.

    /**
     * Init step: provisioning konfiguračního souboru platformbridge.json.php.
     *
     * Po publikování/migraci znovu načte PathResolver a přebuduje provisioners,
     * aby následující kroky pracovaly s cestami z nového konfiguračního souboru.
     */
    private function stepInit(): void
    {
        $result = $this->configProvisioner->provision();

        $this->info($result->message());

        if ($this->force && $result === ProvisionResult::Skipped) {
            $label = PathsConfig::CONFIG_FILE_PROTECTED;
            $this->info("ℹ️  --force does not overwrite {$label} (user-maintained file)");
        }

        // Znovu načti PathResolver – konfigurační soubor nyní existuje
        if ($result->requiresReload()) {
            $this->reloadPaths();
        }
    }

    /**
     * Dirs step: vytvoření adresářové struktury.
     */
    private function stepDirs(): void
    {
        foreach ($this->directoryProvisioner->ensureStructure() as $result) {
            $this->info($result->message());
        }
    }

    /**
     * Guard step: ochranné index.php soubory v citlivých adresářích.
     */
    private function stepGuards(): void
    {
        foreach ($this->directoryProvisioner->ensureGuards() as $result) {
            $this->info($result->message());
        }
    }

    /**
     * Assets step: publikace dist JS/CSS do cílové složky.
     */
    private function stepAssets(): void
    {
        foreach ($this->assetProvisioner->provision($this->publisher) as $result) {
            $this->info($result->message());
        }
    }

    /**
     * Vrátí Closure pro publikaci konkrétního stub souboru (api/config/security).
     */
    private function stepFile(string $name): \Closure
    {
        return function () use ($name): void {
            $result = $this->fileProvisioner->provision($name, $this->publisher, $this->force);
            $this->info($result->message());
        };
    }

    /**
     * Cache step: zajištění existence cache adresáře.
     */
    private function stepCache(): void
    {
        $result = $this->directoryProvisioner->ensureCache();
        $this->info($result->message());
    }

    /**
     * Translations step: provisioning tabulky pb_translations v databázi.
     *
     * Pokud není k dispozici mysqli připojení, krok se přeskočí.
     * Tabulka se vytvoří pouze pokud neexistuje (idempotentní).
     */
    private function stepTranslations(): void
    {
        $mysqli = $this->resolveMysqli();

        if ($mysqli === null) {
            $this->info("[translations] Skipped — no mysqli connection configured");
            return;
        }

        // $provisioner = new TableProvisioner($mysqli);

        // if ($provisioner->ensure()) {
        //     $this->info("[translations] ✓ Table 'pb_translations' created");
        // } else {
        //     $this->info("[translations] Table 'pb_translations' already exists");
        // }
    }

    /**
     * Pokusí se získat mysqli instanci z bridge-config.php.
     *
     * Bridge-config může definovat 'mysqli' callback nebo instanci.
     * Pokud neexistuje nebo není nakonfigurována, vrátí null.
     */
    private function resolveMysqli(): ?\mysqli
    {
        $configFile = $this->paths->bridgeConfigFile();

        if (!file_exists($configFile)) {
            return null;
        }

        try {
            $config = require $configFile;
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($config)) {
            return null;
        }

        $mysqli = $config['mysqli'] ?? null;

        if ($mysqli instanceof \Closure) {
            $mysqli = $mysqli();
        }

        return $mysqli instanceof \mysqli ? $mysqli : null;
    }

    // ─── Internal helpers ────────────────────────────────────

    /**
     * Vytvoří/znovu vytvoří všechny provisioner instance.
     *
     * Volá se v konstruktoru a po reloadPaths() (když se změní konfigurace).
     */
    private function buildProvisioners(): void
    {
		$projectRoot = $this->paths->projectRoot();
        $stubsPath = $this->paths->packageStubsPath();
        $config = $this->paths->pathsConfig();

        $this->configProvisioner = new ConfigProvisioner($projectRoot);

        $this->fileProvisioner = new FileProvisioner($stubsPath, $projectRoot, $config);

        $this->assetProvisioner = new AssetProvisioner(
            distPath: $this->paths->packageRoot() . DIRECTORY_SEPARATOR . 'dist',
            targetPath: $this->paths->assetsPath(),
            label: $config->assets(),
        );

        $this->directoryProvisioner = new DirectoryProvisioner($projectRoot, $config);
    }

    /**
     * Znovu načte PathResolver a přebuduje všechny provisioner instance.
     *
     * Volá se po provisioning konfiguračního souboru, aby následující
     * install kroky pracovaly s cestami z nového/zmigrovaného souboru.
     */
    private function reloadPaths(): void
    {
        $this->paths = PathResolverFactory::auto($this->paths->packageRoot());
        $this->buildProvisioners();
    }

    /**
     * Spustí krok pouze pokud je v --only filtru (nebo pokud filtr není nastaven).
     */
    private function runStep(string $name, \Closure $callback): void
    {
        if ($this->only !== [] && !in_array($name, $this->only, true)) {
            return;
        }
        $callback();
    }

    private function info(string $message): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * Autodetekce package root z umístění třídy.
     * Installer.php žije v src/PlatformBridge/Installer/ → 3× dirname.
     */
    private static function detectPackageRoot(): string
    {
        return dirname(__DIR__, 3);
    }
}