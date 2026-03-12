# PlatformBridge – Architektonický návrh refaktoru

> Verze: 1.0 | Datum: 2026-03-11
> Status: NÁVRH – implementovatelný postupně

---

## 1. Finální struktura balíčku

```
zoom/platform-bridge/
│
├── bin/
│   └── platformbridge                  # CLI entry point
│
├── config/
│   ├── bridge-config.php               # VÝCHOZÍ konfigurace (NIKDY se nepublikuje přímo)
│   └── handlers.php                    # Registrace field handlerů (nově extrahováno)
│
├── dist/                               # ✅ PŘEDKOMPILOVANÉ produkční assety
│   ├── js/
│   │   └── pb-main.js
│   └── css/
│       └── pb-main.css
│
├── resources/
│   ├── defaults/                       # Výchozí JSON konfigurace (read-only, referenční)
│   │   ├── blocks.json
│   │   ├── layouts.json
│   │   └── generators.json
│   ├── stubs/                          # Šablony pro publikování
│   │   ├── api.php
│   │   ├── bridge-config.php           # Stub pro uživatelskou konfiguraci
│   │   └── generators.json             # Stub pro uživatelský override JSON
│   └── views/                          # Šablony template engine
│       ├── Atoms/
│       ├── Components/
│       └── Element/
│
├── src/
│   └── PlatformBridge/
│       ├── PlatformBridge.php
│       ├── PlatformBridgeBuilder.php
│       ├── PlatformBridgeConfig.php
│       ├── AI/
│       ├── Asset/
│       ├── Config/
│       │   ├── ConfigManager.php
│       │   ├── ConfigLoader.php        # Nově: podpora merge strategie
│       │   ├── ConfigResolver.php
│       │   ├── ConfigValidator.php
│       │   ├── ConfigKeys.php
│       │   ├── PathResolver.php        # ✅ NOVÝ – centrální resoluce cest
│       │   └── Exception/
│       ├── Error/
│       ├── Form/
│       ├── Handler/
│       ├── Installer/
│       │   ├── Installer.php           # Refaktorovaný
│       │   ├── PublishManager.php       # ✅ NOVÝ – logika publikování souborů
│       │   └── StubPublisher.php        # ✅ NOVÝ – safe-copy se skip logikou
│       ├── Runtime/
│       ├── Security/
│       ├── Template/
│       └── Translator/
│
├── composer.json
├── .gitattributes                      # ✅ NOVÝ – exclude dev souborů z dist
└── LICENSE
```

### Co NENÍ v balíčku (vývojové soubory)

```
# Existují POUZE v git repozitáři, NE v composer distribuci:
assets/                     # TypeScript + SCSS zdrojáky
    ts/
    scss/
build.mjs                  # esbuild konfigurace
tsconfig.json
package.json
package-lock.json
node_modules/
demo.php
index.php
docs/
var/
public/                     # Nahrazeno dist/ v balíčku
```

---

## 2. Asset workflow

### Princip: `dist/` jako součást balíčku

```
VÝVOJ (git repo):
  assets/ts/*.ts  ──┐
  assets/scss/*.scss┘──► build.mjs ──► dist/js/ + dist/css/

DISTRIBUCE (composer):
  dist/js/pb-main.js      ← předkompilované, commitnuté v git
  dist/css/pb-main.css     ← předkompilované, commitnuté v git
```

### Změna v `build.mjs`

```javascript
import esbuild from "esbuild";
import { sassPlugin } from "esbuild-sass-plugin";

const watch = process.argv.includes("--watch");
const isProd = process.argv.includes("--prod");

// ✅ Output vždy do dist/ (ne do public/)
const tsCtx = await esbuild.context({
    entryPoints: ["assets/ts/pb-main.ts"],
    outdir: "dist/js",              // ← ZMĚNA
    bundle: true,
    minify: isProd,
    sourcemap: !isProd,
    entryNames: "[name]",
    tsconfig: "tsconfig.json"
});

const scssCtx = await esbuild.context({
    entryPoints: ["assets/scss/pb-main.scss"],
    entryNames: "[name]",
    outdir: "dist/css",             // ← ZMĚNA
    bundle: true,
    minify: isProd,
    sourcemap: !isProd,
    plugins: [sassPlugin({ loadPaths: ["assets/scss"] })]
});

if (watch) {
    await tsCtx.watch();
    await scssCtx.watch();
    console.log("[esbuild] Watching for changes...");
} else {
    await tsCtx.rebuild();
    await scssCtx.rebuild();
    await tsCtx.dispose();
    await scssCtx.dispose();
    console.log("[esbuild] Build complete → dist/");
}
```

### Workflow

| Akce | Kdy | Kdo |
|------|-----|-----|
| `npm run dev` | Vývoj | Vývojář balíčku |
| `npm run build` | Před commitem / před tagem | Vývojář balíčku |
| `composer require zoom/platform-bridge` | Instalace | Uživatel |
| `php vendor/bin/platformbridge install` | Po instalaci | Uživatel |

**Uživatel nikdy nepotřebuje Node.js.** Soubory v `dist/` jsou commitnuté v gitu a součástí composer distribuce.

---

## 3. Installer – nový design

### CLI příkazy

```
platformbridge install          # Publikuje: assety, API endpoint, konfiguraci
platformbridge update           # Publikuje: assety, API endpoint (NE konfiguraci)
platformbridge publish:config   # Publikuje POUZE konfiguraci (bridge-config.php)
platformbridge publish:json     # Publikuje POUZE JSON soubory (generators, blocks, layouts)
```

### Co se publikuje a kam

```
HOST APLIKACE (po install):
{projectRoot}/
├── config/
│   └── platform-bridge/
│       ├── bridge-config.php       ← stub, uživatel upravuje, NIKDY se nepřepíše
│       ├── blocks.json             ← stub kopie, uživatel může upravit
│       ├── layouts.json            ← stub kopie, uživatel může upravit
│       └── generators.json         ← stub kopie, uživatel může upravit
├── public/
│   └── platformbridge/
│       ├── api.php                 ← stub, nepřepíše se pokud existuje
│       ├── js/
│       │   └── pb-main.js         ← z dist/, VŽDY se přepíše při update
│       └── css/
│           └── pb-main.css         ← z dist/, VŽDY se přepíše při update
└── var/
    └── cache/                      ← template cache (auto-created)
```

### Co zůstane ve vendor

```
vendor/zoom/platform-bridge/
├── bin/platformbridge
├── config/bridge-config.php        ← výchozí referenční hodnoty
├── dist/js/ + css/                 ← zdrojové assety pro publish
├── resources/defaults/*.json       ← výchozí JSON (fallback)
├── resources/stubs/*               ← šablony pro publish
├── resources/views/                ← šablony template engine (runtime)
└── src/                            ← PHP kód (runtime)
```

### Nový `PathResolver`

```php
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
```

### Nový `StubPublisher`

```php
<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Installer;

/**
 * Bezpečné publikování souborů se skip logikou.
 *
 * Pravidla:
 *   - overwrite: true  → soubor se VŽDY přepíše (assety)
 *   - overwrite: false → soubor se přeskočí pokud existuje (konfigurace)
 */
final class StubPublisher
{
    /** @var list<array{source: string, target: string, overwrite: bool}> */
    private array $published = [];

    /**
     * Publikuje soubor. Vrátí true pokud byl soubor zapsán.
     */
    public function publish(string $source, string $target, bool $overwrite = false): bool
    {
        if (!file_exists($source)) {
            throw new \RuntimeException("Source file not found: {$source}");
        }

        if (!$overwrite && file_exists($target)) {
            $this->published[] = ['source' => $source, 'target' => $target, 'skipped' => true];
            return false;
        }

        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        copy($source, $target);
        $this->published[] = ['source' => $source, 'target' => $target, 'skipped' => false];
        return true;
    }

    /**
     * Publikuje celý adresář.
     */
    public function publishDirectory(string $source, string $target, bool $overwrite = false): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relative = substr($item->getPathname(), strlen($source) + 1);
                if ($this->publish($item->getPathname(), $target . DIRECTORY_SEPARATOR . $relative, $overwrite)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /** @return list<array{source: string, target: string, skipped: bool}> */
    public function getLog(): array
    {
        return $this->published;
    }
}
```

### Refaktorovaný `Installer`

```php
<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Installer;

use Zoom\PlatformBridge\Config\PathResolver;

final class Installer
{
    private PathResolver $paths;
    private StubPublisher $publisher;

    public function __construct(?string $packageRoot = null)
    {
        $this->paths = new PathResolver($packageRoot);
        $this->publisher = new StubPublisher();
    }

    /**
     * Kompletní instalace – assety + API + konfigurace.
     * Konfigurace se NEPŘEPÍŠE pokud existuje.
     */
    public function install(): void
    {
        $this->info("PlatformBridge Installer");
        $this->info("========================");
        $this->info("Mode: " . ($this->paths->isVendor() ? 'vendor' : 'standalone'));
        $this->info("");

        $this->publishAssets();
        $this->publishApiEndpoint();
        $this->publishConfig();
        $this->publishJson();
        $this->ensureCacheDir();

        $this->printLog();
        $this->info("\n✅ PlatformBridge installed successfully!");
    }

    /**
     * Update – přepíše assety a API, ale NE konfiguraci a JSON.
     */
    public function update(): void
    {
        $this->info("PlatformBridge Updater");
        $this->info("======================");

        $this->publishAssets();
        $this->publishApiEndpoint();

        $this->printLog();
        $this->info("\n✅ PlatformBridge updated!");
    }

    /**
     * Publikuje bridge-config.php (bez přepisu).
     */
    public function publishConfig(): void
    {
        $stub = $this->paths->packageStubsPath() . '/bridge-config.php';
        $target = $this->paths->userBridgeConfigFile();

        $written = $this->publisher->publish($stub, $target, overwrite: false);
        $this->info($written
            ? "  ✅ Published: config/platform-bridge/bridge-config.php"
            : "  ⏭️  Skipped:   config/platform-bridge/bridge-config.php (exists)"
        );
    }

    /**
     * Publikuje JSON soubory (bez přepisu).
     */
    public function publishJson(): void
    {
        $defaults = $this->paths->packageDefaultsPath();
        $target = $this->paths->userJsonPath();

        foreach (['blocks.json', 'layouts.json', 'generators.json'] as $file) {
            $written = $this->publisher->publish(
                $defaults . '/' . $file,
                $target . '/' . $file,
                overwrite: false
            );
            $this->info($written
                ? "  ✅ Published: config/platform-bridge/{$file}"
                : "  ⏭️  Skipped:   config/platform-bridge/{$file} (exists)"
            );
        }
    }

    /**
     * Publikuje dist/ assety do public/ (VŽDY přepíše).
     */
    private function publishAssets(): void
    {
        $distPath = $this->paths->packageDistPath();
        $targetPath = $this->paths->publicAssetsPath();

        foreach (['js', 'css'] as $dir) {
            $source = $distPath . '/' . $dir;
            if (is_dir($source)) {
                $count = $this->publisher->publishDirectory($source, $targetPath . '/' . $dir, overwrite: true);
                $this->info("  ✅ Published: public/platformbridge/{$dir}/ ({$count} files)");
            }
        }
    }

    /**
     * Publikuje API endpoint (bez přepisu).
     */
    private function publishApiEndpoint(): void
    {
        $stub = $this->paths->packageStubsPath() . '/api.php';
        $target = $this->paths->publicAssetsPath() . '/api.php';

        $written = $this->publisher->publish($stub, $target, overwrite: false);
        $this->info($written
            ? "  ✅ Published: public/platformbridge/api.php"
            : "  ⏭️  Skipped:   public/platformbridge/api.php (exists)"
        );
    }

    private function ensureCacheDir(): void
    {
        $cache = $this->paths->cachePath();
        if (!is_dir($cache)) {
            mkdir($cache, 0755, true);
            $this->info("  ✅ Created: var/cache/");
        }
    }

    private function printLog(): void
    {
        // Optional: detailed log output
    }

    private function info(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
```

---

## 4. Konfigurace – `bridge-config.php`

### Princip: stub → user copy → fallback na package default

**Ve vendor/zoom/platform-bridge/config/bridge-config.php:**
```php
<?php
// Výchozí referenční konfigurace balíčku.
// Uživatel by měl spustit: php vendor/bin/platformbridge install
// a upravit: config/platform-bridge/bridge-config.php

if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

return [
    'secretKey'   => 'CHANGE-ME-put-your-long-super-secret-key-here-32chars-minimum',
    'ttl'         => 3600,
    'api_key'     => 'YOUR_API_KEY_HERE',
    'timeout'     => 30,
    'max_retries' => 3,
    'base_url'    => (function () {
        $isHttps  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $protocol = $isHttps ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}";
    })(),
];
```

**Ve resources/stubs/bridge-config.php** (kopíruje se do host projektu):
```php
<?php
/**
 * PlatformBridge – Uživatelská konfigurace
 *
 * Tento soubor byl vygenerován příkazem:
 *   php vendor/bin/platformbridge install
 *
 * Upravte hodnoty podle svého prostředí.
 * Tento soubor se NEPŘEPISUJE při composer update.
 */

if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

$isHttps  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$protocol = $isHttps ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseHost = "{$protocol}://{$host}";

return [
    // ─── HMAC Podpis ────────────────────────────────────────────
    'secretKey' => 'CHANGE-ME-put-your-long-super-secret-key-here-32chars-minimum',
    'ttl'       => 3600,

    // ─── AI Provider ────────────────────────────────────────────
    'api_key'     => 'YOUR_API_KEY_HERE',
    'timeout'     => 30,
    'max_retries' => 3,
    'base_url'    => $baseHost . '/api/ai',
];
```

### Resoluce v `PlatformBridgeBuilder`

```php
// PlatformBridgeBuilder::resolveBridgeConfigPath()
private function resolveBridgeConfigPath(): string
{
    // 1. Explicitně nastavená cesta
    if ($this->bridgeConfigPath !== null) {
        return $this->bridgeConfigPath;
    }

    // 2. PathResolver: user config → package fallback
    $resolver = new PathResolver(dirname(__DIR__, 2));
    return $resolver->resolvedBridgeConfigFile();
}
```

---

## 5. JSON konfigurace – merge strategie

### Problém

Uživatel chce upravit `generators.json` (přidat vlastní generátor), ale nechce přijít o změny při update.

### Řešení: User-first s package fallbackem

```php
<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config;

/**
 * ConfigLoader s podporou merge strategie.
 *
 * Priorita:
 *   1. Uživatelský soubor (config/platform-bridge/blocks.json)
 *   2. Package default  (vendor/.../resources/defaults/blocks.json)
 *
 * Strategie:
 *   - Pokud existuje uživatelský soubor → použij POUZE ten (full override)
 *   - Pokud neexistuje → použij package default
 *
 * Toto je záměrně FULL OVERRIDE, ne merge. Uživatel má plnou kontrolu.
 * Merge by byl nebezpečný (konflikty klíčů, neočekávané chování).
 */
final class ConfigLoader
{
    public function __construct(
        private readonly string $userConfigPath,
        private readonly string $packageDefaultsPath,
    ) {}

    /**
     * Načte JSON soubor s fallbackem na package defaults.
     *
     * @param string $filename Název souboru (např. 'blocks.json')
     * @return array Parsovaná data
     * @throws ConfigException Pokud soubor neexistuje ani ve fallbacku
     */
    public function load(string $filename): array
    {
        // 1. User override
        $userFile = $this->userConfigPath . '/' . $filename;
        if (file_exists($userFile)) {
            return $this->parseJson($userFile);
        }

        // 2. Package default
        $defaultFile = $this->packageDefaultsPath . '/' . $filename;
        if (file_exists($defaultFile)) {
            return $this->parseJson($defaultFile);
        }

        throw ConfigException::fileNotFound($filename);
    }

    /**
     * Zjistí, zda je používán uživatelský override.
     */
    public function isUserOverride(string $filename): bool
    {
        return file_exists($this->userConfigPath . '/' . $filename);
    }

    /**
     * Načte RAW obsah souboru.
     */
    public function loadRaw(string $filename): string
    {
        $userFile = $this->userConfigPath . '/' . $filename;
        if (file_exists($userFile)) {
            return file_get_contents($userFile);
        }

        $defaultFile = $this->packageDefaultsPath . '/' . $filename;
        if (file_exists($defaultFile)) {
            return file_get_contents($defaultFile);
        }

        throw ConfigException::fileNotFound($filename);
    }

    private function parseJson(string $path): array
    {
        $content = file_get_contents($path);
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
```

### Změna v `ConfigManager`

```php
// Stávající:
public function __construct(string $configPath)

// Nový:
public function __construct(
    private readonly ConfigLoader $loader,
) {}

// V boot() PlatformBridge:
$loader = new ConfigLoader(
    userConfigPath: $paths->userConfigPath(),         // config/platform-bridge/
    packageDefaultsPath: $paths->packageDefaultsPath() // vendor/.../resources/defaults/
);
$this->configManager = new ConfigManager($loader);
```

---

## 6. API entrypointy – bootstrap z vendor

### `resources/stubs/api.php`

```php
<?php
/**
 * PlatformBridge API Endpoint
 *
 * Publikováno příkazem: php vendor/bin/platformbridge install
 * Umístění: {projectRoot}/public/platformbridge/api.php
 *
 * Můžete přidat vlastní middleware, autentizaci apod.
 * Tento soubor se NEPŘEPISUJE při composer update.
 */

// ─── Autoloader ─────────────────────────────────────────────────
// Relativní cesta: public/platformbridge/api.php → vendor/autoload.php
$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'api' => [
            'success' => false,
            'error' => [
                'type' => 'configuration',
                'message' => 'Autoloader not found. Run "composer install" first.',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
}

require_once $autoloadPath;

// ─── Bootstrap ──────────────────────────────────────────────────
// ApiHandler automaticky detekuje cesty přes PathResolver.
// Konfigurace se načte z config/platform-bridge/bridge-config.php
// s fallbackem na vendor defaults.

\Zoom\PlatformBridge\AI\API\ApiHandler::bootstrap()->handle();
```

### Úprava `ApiHandler::bootstrap()`

```php
public static function bootstrap(): self
{
    // PathResolver automaticky detekuje vendor/standalone režim
    $paths = new \Zoom\PlatformBridge\Config\PathResolver();

    if (!defined('BRIDGE_BOOTSTRAPPED')) {
        define('BRIDGE_BOOTSTRAPPED', true);
    }

    // Konfigurace se načte s fallbackem: user → package
    $configFile = $paths->resolvedBridgeConfigFile();
    $config = require $configFile;

    $loader = new \Zoom\PlatformBridge\Config\ConfigLoader(
        $paths->userConfigPath(),
        $paths->packageDefaultsPath(),
    );

    // ... zbytek bootstrapu
    return new self($config, $loader);
}
```

---

## 7. Distribuce – `.gitattributes`

```gitattributes
# ─── Exclude from Composer distribution ──────────────────────────
# Tyto soubory existují v git repozitáři ale NE v composer install.
# Funguje s `composer install --prefer-dist` (výchozí).

# Vývojové soubory
/assets                 export-ignore
/build.mjs              export-ignore
/tsconfig.json          export-ignore
/package.json           export-ignore
/package-lock.json      export-ignore

# Testy a dokumentace
/docs                   export-ignore
/demo.php               export-ignore
/index.php              export-ignore

# Runtime cache a temp soubory
/var                    export-ignore
/node_modules           export-ignore

# CI/CD
/.github                export-ignore

# Staré public/ (nahrazeno dist/)
/public                 export-ignore
```

### Úprava `composer.json`

```json
{
    "name": "zoom/platform-bridge",
    "description": "AI-powered form builder with template engine and field factory",
    "type": "library",
    "license": "proprietary",
    "minimum-stability": "stable",
    "prefer-stable": true,
    "require": {
        "php": ">=8.1",
        "ext-curl": "*",
        "ext-json": "*"
    },
    "autoload": {
        "psr-4": {
            "Zoom\\PlatformBridge\\": "src/PlatformBridge/"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true
    },
    "scripts": {
        "post-install-cmd": [
            "@php vendor/zoom/platform-bridge/bin/platformbridge install"
        ],
        "post-update-cmd": [
            "@php vendor/zoom/platform-bridge/bin/platformbridge update"
        ]
    },
    "extra": {
        "branch-alias": {
            "dev-main": "1.0.x-dev"
        }
    },
    "bin": [
        "bin/platformbridge"
    ]
}
```

**Klíčové změny:**
- `post-install-cmd` → `install` (publikuje vše včetně konfigurace, přeskočí existující)
- `post-update-cmd` → `update` (přepíše POUZE assety a API, NE konfiguraci)
- Odstraněn `archive` blok → nahrazen `.gitattributes`
- Odstraněn `full-install` z hooks (žádný npm při composer install)

---

## 8. Migrace `PlatformBridgeBuilder` na `PathResolver`

```php
final class PlatformBridgeBuilder
{
    private ?string $configPath = null;
    private ?string $viewsPath = null;
    private ?string $cachePath = null;
    private ?string $bridgeConfigPath = null;
    private ?string $assetUrl = null;
    private bool $useHmac = false;
    private ?int $paramsTtl = null;
    private string $locale = 'cs';

    // PathResolver se vytvoří jednou a sdílí
    private PathResolver $paths;

    public function __construct()
    {
        $this->paths = new PathResolver(dirname(__DIR__, 2));
    }

    // ... withXxx() metody zůstávají stejné ...

    public function build(): PlatformBridge
    {
        $config = new PlatformBridgeConfig(
            configPath:       $this->configPath       ?? $this->paths->resolvedConfigPath(),
            viewsPath:        $this->viewsPath        ?? $this->paths->packageViewsPath(),
            cachePath:        $this->cachePath         ?? $this->paths->cachePath(),
            locale:           $this->locale,
            bridgeConfigPath: $this->bridgeConfigPath  ?? $this->paths->resolvedBridgeConfigFile(),
            assetUrl:         $this->assetUrl          ?? $this->resolveAssetUrl(),
            useHmac:          $this->useHmac,
            paramsTtl:        $this->paramsTtl,
        );

        return PlatformBridge::fromConfig($config);
    }

    private function resolveAssetUrl(): string
    {
        // Stávající auto-detekce z Installer::getDefaultAssetUrl()
        // přesunutá do PathResolver nebo ponechaná v AssetManager
        return Installer::getDefaultAssetUrl($this->paths->packageRoot());
    }
}
```

---

## 9. Implementační plán (postupná migrace)

### Fáze 1: Příprava struktury (bez breaking changes)

| # | Akce | Dopad |
|---|------|-------|
| 1 | Vytvořit `dist/` a změnit `build.mjs` output | Žádný – nová složka |
| 2 | Commitnout `dist/js/` a `dist/css/` do gitu | Žádný |
| 3 | Vytvořit `.gitattributes` | Žádný – ovlivní pouze nové instalace |
| 4 | Vytvořit `config/bridge-config.php` (kopie z resources) | Žádný |
| 5 | Vytvořit `resources/stubs/bridge-config.php` | Žádný – nový soubor |

### Fáze 2: PathResolver + ConfigLoader

| # | Akce | Dopad |
|---|------|-------|
| 6 | Implementovat `PathResolver` | Žádný – nová třída |
| 7 | Implementovat nový `ConfigLoader` s fallback logikou | Interní refaktor |
| 8 | Upravit `ConfigManager` aby používal nový `ConfigLoader` | Interní refaktor |
| 9 | Upravit `PlatformBridgeBuilder` na `PathResolver` | Interní refaktor |

### Fáze 3: Installer

| # | Akce | Dopad |
|---|------|-------|
| 10 | Implementovat `StubPublisher` | Žádný – nová třída |
| 11 | Refaktorovat `Installer` | Změní chování CLI |
| 12 | Přidat `publish:config` a `publish:json` příkazy | Nová funkčnost |
| 13 | Aktualizovat `composer.json` scripts | Změní post-install/update |

### Fáze 4: Cleanup

| # | Akce | Dopad |
|---|------|-------|
| 14 | Odebrat `fullInstall()` a npm logiku z `Installer` | Cleanup |
| 15 | Přesunout `bridge-config.php` z `resources/config/` do `config/` | Struktura |
| 16 | Odebrat `public/` z git (nahrazeno `dist/`) | Breaking pro stávající dev setup |
| 17 | Aktualizovat `demo.php` a `index.php` pro nové cesty | Dev only |

---

## 10. Souhrn klíčových principů

```
┌─────────────────────────────────────────────────────────────┐
│                    DISTRIBUCE (composer)                      │
│                                                               │
│  dist/          → předkompilované assety (commitnuté v git)  │
│  config/        → výchozí reference (fallback)               │
│  resources/     → stubs, views, defaults                     │
│  src/           → PHP runtime kód                            │
│  bin/           → CLI                                         │
│                                                               │
│  ❌ assets/, build.mjs, node_modules, package.json           │
│  ❌ docs/, demo.php, var/, public/                           │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    HOST APLIKACE (po install)                 │
│                                                               │
│  config/platform-bridge/                                      │
│    bridge-config.php      ← UŽIVATEL UPRAVUJE               │
│    blocks.json            ← UŽIVATEL MŮŽE UPRAVIT            │
│    layouts.json           ← UŽIVATEL MŮŽE UPRAVIT            │
│    generators.json        ← UŽIVATEL MŮŽE UPRAVIT            │
│                                                               │
│  public/platformbridge/                                       │
│    api.php                ← stub, nepřepíše se               │
│    js/pb-main.js          ← z dist/, přepíše se při update   │
│    css/pb-main.css        ← z dist/, přepíše se při update   │
│                                                               │
│  var/cache/               ← template cache                   │
│                                                               │
│  ✅ composer update NEPŘEPÍŠE konfiguraci                    │
│  ✅ composer update NEPŘEPÍŠE JSON soubory                   │
│  ✅ composer update AKTUALIZUJE assety                       │
│  ✅ Žádný npm/Node.js potřeba                                │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    RESOLUCE CEST                              │
│                                                               │
│  PathResolver detekuje režim:                                │
│    vendor:     vendor/zoom/platform-bridge/ → project root   │
│    standalone: kořen projektu = package root                 │
│                                                               │
│  Fallback řetězec:                                           │
│    1. Explicitní cesta (withConfigPath())                    │
│    2. Uživatelský soubor (config/platform-bridge/*)          │
│    3. Package default (vendor/.../resources/defaults/*)      │
└─────────────────────────────────────────────────────────────┘
```
