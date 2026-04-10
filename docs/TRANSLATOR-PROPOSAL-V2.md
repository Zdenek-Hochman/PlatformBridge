# PlatformBridge – Translator systém v2

> Verze: 2.0 | Datum: 2026-03-31
> Status: NÁVRH (přepracovaný)

---

## 0. Klíčové změny oproti v1

| Oblast | v1 | v2 (tento návrh) |
|---|---|---|
| Frontend překlady | `<script>window.__PB_TRANSLATIONS__</script>` inline | **Fetch API** + lokální cache s hash invalidací |
| TS Translator | Vanilla singleton, čte z `window` | **Globální třída** v bootstrap pipeline |
| Config texty | `translatable` flag v JSON | **Proměnné** `{$klíč}` přímo v JSON hodnotách |
| DB adaptery | mysqli, PostgreSQL, Redis, custom | **Pouze mysqli** — žádné jiné DB |
| Tabulky | Adaptér na cizí strukturu | **Jedna univerzální tabulka** `pb_translations` |
| Instalace tabulky | Ruční SQL | **Automatická kontrola a vytvoření** při instalaci |

---

## 1. Databázová vrstva

### 1.1. Proč jedna univerzální tabulka

Adaptovat se na existující tabulkové struktury různých aplikací je extrémně komplexní:
- Každá app má jiné schema (sloupce, typy, relace)
- Mapování sloupců by vyžadovalo config vrstvu nad configem
- Edge cases (joinované tabulky, EAV modely, JSON sloupce...) jsou nekonečné
- Údržba by převýšila benefit

**Řešení:** Jedna tabulka `pb_translations`, kterou PlatformBridge sám vytvoří a spravuje. Aplikace ji nemusí znát — je to interní úložiště.

### 1.2. Struktura tabulky

```sql
CREATE TABLE IF NOT EXISTS `pb_translations` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `locale`     VARCHAR(10)  NOT NULL,                      -- 'cs', 'en', 'de', 'pt-BR'
    `domain`     VARCHAR(20)  NOT NULL,                      -- 'errors', 'ui', 'api', 'config'
    `key_path`   VARCHAR(255) NOT NULL,                      -- 'http.400', 'blocks.tone.label'
    `value`      TEXT         NOT NULL,                      -- přeložený text
    `hash`       CHAR(8)      NOT NULL,                      -- crc32 hash value (pro cache invalidaci)
    `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_translation` (`locale`, `domain`, `key_path`),
    INDEX `idx_locale_domain` (`locale`, `domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Proč `hash` sloupec:**
- Frontend cache systém potřebuje vědět, jestli se překlady změnily
- Místo porovnávání celého obsahu se porovnává hash
- `crc32` je rychlý a pro tento účel dostačující (8 hex znaků)
- Hash se počítá z `value` při INSERT/UPDATE

### 1.3. Automatická instalace tabulky

Kontrola a vytvoření tabulky probíhá:
1. **Při instalaci** — nový krok `translations` v Installeru
2. **Při bootu** (lazy) — pokud je předána mysqli instance, ověří se existence tabulky

```php
<?php
namespace PlatformBridge\Translator\Database;

/**
 * Zajišťuje existenci tabulky pb_translations.
 * Bezpečný pro opakované volání (idempotentní).
 */
final class TableProvisioner
{
    private const DEFAULT_TABLE = 'pb_translations';

    public function __construct(
        private readonly \mysqli $mysqli,
        private readonly string $tableName = self::DEFAULT_TABLE,
    ) {}

    /**
     * Ověří zda tabulka existuje a případně ji vytvoří.
     * @return bool true = tabulka byla právě vytvořena, false = již existovala
     */
    public function ensure(): bool
    {
        if ($this->exists()) {
            return false;
        }

        $this->create();
        return true;
    }

    /**
     * Kontroluje existenci tabulky.
     */
    public function exists(): bool
    {
        $escaped = $this->mysqli->real_escape_string($this->tableName);
        $result = $this->mysqli->query(
            "SHOW TABLES LIKE '{$escaped}'"
        );

        return $result !== false && $result->num_rows > 0;
    }

    /**
     * Vytvoří tabulku s překladovým schema.
     * @throws \RuntimeException Pokud CREATE selže
     */
    private function create(): void
    {
        $table = $this->escapeIdentifier($this->tableName);

        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS {$table} (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `locale`     VARCHAR(10)  NOT NULL,
            `domain`     VARCHAR(20)  NOT NULL,
            `key_path`   VARCHAR(255) NOT NULL,
            `value`      TEXT         NOT NULL,
            `hash`       CHAR(8)      NOT NULL,
            `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_translation` (`locale`, `domain`, `key_path`),
            INDEX `idx_locale_domain` (`locale`, `domain`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        if ($this->mysqli->query($sql) === false) {
            throw new \RuntimeException(
                "Failed to create translations table: " . $this->mysqli->error
            );
        }
    }

    private function escapeIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
```

### 1.4. Integrace do Installeru

Nový krok `translations` v `Installer::install()`:

```php
// Installer.php — nový krok po 'security':
// Kroky: init → dirs → guard → assets → api → config → security → translations → cache

$this->runStep('translations', $this->stepTranslations(...));

private function stepTranslations(): void
{
    // Installer potřebuje mysqli instanci
    // → přes bridge-config.php nebo přímé předání
    $mysqli = $this->resolveMysqli();

    if ($mysqli === null) {
        $this->info("[translations] Skipped — no mysqli connection configured");
        return;
    }

    $provisioner = new TableProvisioner($mysqli);

    if ($provisioner->ensure()) {
        $this->info("[translations] ✓ Table 'pb_translations' created");
    } else {
        $this->info("[translations] Table 'pb_translations' already exists");
    }
}
```

---

## 2. Proměnné v JSON místo `translatable`

### 2.1. Princip

Místo klíče `translatable`, který označuje co překládat, se do JSON hodnot vloží **proměnné**. Proměnná je placeholder ve formátu `{$domain.key_path}`, který se za běhu nahradí překladem.

**Výhody:**
- Není potřeba žádný speciální flag — proměnná = přeložitelné
- Nové klíče v budoucnu automaticky fungují
- Explicitní — v JSON je jasně vidět, co bude přeložené
- Fallback systém — pokud překlad neexistuje, proměnná se nahradí svým klíčem nebo výchozí hodnotou

### 2.2. Syntaxe proměnných

```
{$doména.klíč}              → hledá překlad v dané doméně
{$doména.klíč|Fallback}     → pokud překlad neexistuje, použije "Fallback"
```

Příklady:
```
{$config.blocks.tone.label}                 → "Tón komunikace" (cs) / "Tone" (en)
{$config.blocks.tone.label|Tone}            → "Tón komunikace" nebo "Tone" jako fallback
{$ui.form.required}                         → "Toto pole je povinné"
{$errors.http.400|Bad request}              → "Neplatný požadavek." nebo "Bad request"
```

### 2.3. blocks.json — před a po

**Před (v1 s `translatable`):**
```json
{
    "tone": {
        "id": "tone",
        "name": "tone",
        "component": "select",
        "label": "Tone",
        "translatable": ["label", "options"],
        "options": [
            { "value": "friendly", "label": "Friendly" },
            { "value": "formal", "label": "Formal" }
        ]
    }
}
```

**Po (v2 s proměnnými):**
```json
{
    "tone": {
        "id": "tone",
        "name": "tone",
        "component": "select",
        "label": "{$config.blocks.tone.label|Tone}",
        "options": [
            { "value": "friendly", "label": "{$config.blocks.tone.options.friendly|Friendly}" },
            { "value": "formal", "label": "{$config.blocks.tone.options.formal|Formal}" }
        ]
    }
}
```

### 2.4. Resolver proměnných

```php
<?php
namespace PlatformBridge\Translator;

/**
 * Rozpozná a nahrazuje překladové proměnné {$domain.key} v textech.
 *
 * Vzor: {$doména.klíč.cesta}        → překlad nebo klíč
 *        {$doména.klíč.cesta|Default} → překlad nebo "Default"
 */
final class VariableResolver
{
    /**
     * Regex pro rozpoznání proměnných.
     * Matchuje: {$config.blocks.tone.label} nebo {$config.blocks.tone.label|Fallback text}
     */
    private const PATTERN = '/\{\$([a-zA-Z0-9_.]+)(?:\|([^}]*))?\}/';

    public function __construct(
        private readonly Translator $translator
    ) {}

    /**
     * Nahradí všechny proměnné v řetězci překlady.
     */
    public function resolve(string $text): string
    {
        return preg_replace_callback(self::PATTERN, function (array $match): string {
            $fullKey = $match[1];         // "config.blocks.tone.label"
            $fallback = $match[2] ?? null; // "Tone" nebo null

            // Rozděl na doménu a klíč
            $dotPos = strpos($fullKey, '.');
            if ($dotPos === false) {
                return $fallback ?? $fullKey;
            }

            $domain = substr($fullKey, 0, $dotPos);
            $key = substr($fullKey, $dotPos + 1);

            return $this->translator->t($domain, $key, [], $fallback);
        }, $text);
    }

    /**
     * Rekurzivně projde pole a nahradí proměnné ve všech string hodnotách.
     * Používá se pro zpracování celého bloku/generátoru z JSON.
     */
    public function resolveArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->resolve($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->resolveArray($value);
            }
        }

        return $data;
    }

    /**
     * Detekuje zda řetězec obsahuje překladovou proměnnou.
     */
    public static function hasVariable(string $text): bool
    {
        return (bool) preg_match(self::PATTERN, $text);
    }
}
```

### 2.5. Použití v ConfigManager/FormRenderer

Namísto specifického `resolveBlockTranslations()` se jednoduše:

```php
// Kdekoliv kde se čte block z JSON:
$block = $this->configManager->getBlock('tone');
$block = $this->variableResolver->resolveArray($block);
// Hotovo — všechny {$...} proměnné jsou nahrazeny překlady
```

Toto funguje **genericky** — nepotřebuje vědět o struktuře bloku, optionu atd.

---

## 3. TS Translator — globální třída s fetch + cache

### 3.1. Architektura

```
┌─────────────────────────────────────────────────────┐
│  App.init()  (DOMContentLoaded)                      │
│    ↓                                                 │
│  Translator.boot(locale, endpoint)                   │
│    ↓                                                 │
│  ┌───────────────────────────────────────────────┐   │
│  │  1. Načti hash z localStorage                 │   │
│  │  2. Fetch endpoint?locale=cs&hash=abc123      │   │
│  │  3a. 304 / {changed: false} → použij cache    │   │
│  │  3b. 200 / {changed: true, ...} → ulož cache  │   │
│  │  4. Translator je připraven                   │   │
│  └───────────────────────────────────────────────┘   │
│    ↓                                                 │
│  Translator.t('errors', 'http.400')                  │
│  Translator.tp('errors', 'ai.timeout', {seconds: 30})│
└─────────────────────────────────────────────────────┘
```

### 3.2. PHP endpoint pro překlady

Nový endpoint (nebo rozšíření stávajícího API) — servíruje překlady pro frontend:

```php
<?php
// api/translations.php (nebo route v api.php)

namespace PlatformBridge\Translator;

/**
 * HTTP endpoint pro frontend překlady.
 *
 * Parametry:
 *   ?locale=cs              (povinný)
 *   &domains=errors,ui      (volitelný, default: errors,ui)
 *   &hash=abc12345          (volitelný, pro cache validaci)
 *
 * Odpovědi:
 *   200 { changed: true,  hash: "xyz...", translations: {...} }
 *   200 { changed: false }
 */
final class TranslationEndpoint
{
    public function __construct(
        private readonly Translator $translator
    ) {}

    public function handle(): void
    {
        $locale = $_GET['locale'] ?? 'en';
        $domains = isset($_GET['domains'])
            ? explode(',', $_GET['domains'])
            : ['errors', 'ui'];
        $clientHash = $_GET['hash'] ?? null;

        // Sestav překlady
        $translations = [];
        foreach ($domains as $domain) {
            $domainEnum = Domain::tryFrom($domain);
            if ($domainEnum !== null) {
                $translations[$domain] = $this->translator->getCatalog()
                    ->allForDomain($domainEnum);
            }
        }

        // Spočítej hash z aktuálních dat
        $serverHash = $this->computeHash($translations);

        // Cache hit — klient má aktuální data
        if ($clientHash !== null && $clientHash === $serverHash) {
            $this->json(['changed' => false]);
            return;
        }

        // Cache miss — pošli nová data
        $this->json([
            'changed'      => true,
            'hash'         => $serverHash,
            'locale'       => $locale,
            'translations' => $translations,
        ]);
    }

    private function computeHash(array $translations): string
    {
        return hash('crc32b', json_encode($translations, JSON_UNESCAPED_UNICODE));
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
```

### 3.3. TS Translator — kompletní implementace

```typescript
// assets/ts/i18n/TranslationCache.ts

interface CachedTranslations {
    hash: string;
    locale: string;
    translations: TranslationMap;
    timestamp: number;
}

type TranslationMap = Record<string, Record<string, string>>;

const STORAGE_KEY = 'pb_translations';
const MAX_AGE_MS = 24 * 60 * 60 * 1000; // 24h hard expiry (safety net)

export class TranslationCache {
    /**
     * Načte překlady z localStorage.
     * Vrátí null pokud:
     *  - cache neexistuje
     *  - cache je pro jiný locale
     *  - cache je starší než MAX_AGE_MS
     */
    static load(locale: string): CachedTranslations | null {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;

            const cached: CachedTranslations = JSON.parse(raw);

            // Jiný locale → invalidace
            if (cached.locale !== locale) return null;

            // Hard expiry (safety net)
            if (Date.now() - cached.timestamp > MAX_AGE_MS) return null;

            return cached;
        } catch {
            return null;
        }
    }

    /**
     * Uloží překlady do localStorage.
     */
    static save(locale: string, hash: string, translations: TranslationMap): void {
        try {
            const data: CachedTranslations = {
                hash,
                locale,
                translations,
                timestamp: Date.now(),
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch {
            // localStorage plný nebo nedostupný — tiché selhání
            console.warn('[Translator] Cache write failed');
        }
    }

    /**
     * Vymaže cache.
     */
    static clear(): void {
        localStorage.removeItem(STORAGE_KEY);
    }
}
```

```typescript
// assets/ts/i18n/Translator.ts

import { TranslationCache } from './TranslationCache';

type TranslationMap = Record<string, Record<string, string>>;

interface TranslationResponse {
    changed: boolean;
    hash?: string;
    locale?: string;
    translations?: TranslationMap;
}

interface TranslatorConfig {
    /** URL endpoint pro překlady */
    endpoint: string;
    /** Locale kód */
    locale: string;
    /** Domény k načtení (default: ['errors', 'ui']) */
    domains?: string[];
    /** Timeout pro fetch v ms (default: 5000) */
    timeout?: number;
}

/**
 * Globální Translator třída s fetch-based načítáním a localStorage cache.
 *
 * Lifecycle:
 *   1. boot() — načte překlady (z cache nebo HTTP)
 *   2. t()    — přeloží klíč
 *   3. tp()   — přeloží klíč s parametry
 *
 * Cache strategie:
 *   - Při bootu se zkontroluje localStorage
 *   - Pokud cache existuje, pošle se hash na server
 *   - Server odpoví: changed=false (použij cache) nebo changed=true (nová data)
 *   - Nová data se uloží do localStorage
 *
 * @example
 * ```ts
 * // V App.init():
 * await Translator.boot({
 *     endpoint: '/api/translations',
 *     locale: 'cs',
 * });
 *
 * // Kdekoliv v kódu:
 * Translator.t('errors', 'http.400');                     // "Neplatný požadavek."
 * Translator.tp('errors', 'ai.timeout', { seconds: 30 }); // "Požadavek vypršel po 30 sekundách"
 * ```
 */
class TranslatorClass {
    private translations: TranslationMap = {};
    private locale: string = 'en';
    private ready: boolean = false;
    private bootPromise: Promise<void> | null = null;

    /**
     * Inicializuje Translator — načte překlady z cache nebo serveru.
     * Tato metoda se volá jednou při startu aplikace.
     *
     * @returns Promise který se vyřeší jakmile jsou překlady dostupné
     */
    async boot(config: TranslatorConfig): Promise<void> {
        // Zabránit dvojímu bootu
        if (this.bootPromise) return this.bootPromise;

        this.bootPromise = this._boot(config);
        return this.bootPromise;
    }

    private async _boot(config: TranslatorConfig): Promise<void> {
        this.locale = config.locale;
        const domains = config.domains ?? ['errors', 'ui'];
        const timeout = config.timeout ?? 5000;

        // 1. Zkontroluj localStorage cache
        const cached = TranslationCache.load(this.locale);

        if (cached) {
            // Máme cache → okamžitě ji použij (optimistic)
            this.translations = cached.translations;
            this.ready = true;
        }

        // 2. Fetch ze serveru (s hashem pro cache validaci)
        try {
            const url = this.buildUrl(config.endpoint, this.locale, domains, cached?.hash);
            const response = await this.fetchWithTimeout(url, timeout);

            if (!response.ok) {
                console.warn(`[Translator] Fetch failed: ${response.status}`);
                // Pokud máme cache, to stačí
                if (cached) return;
                // Jinak tiché selhání — t() vrátí klíče
                this.ready = true;
                return;
            }

            const data: TranslationResponse = await response.json();

            if (data.changed && data.translations && data.hash) {
                // Server vrátil nová data → aktualizuj
                this.translations = data.translations;
                TranslationCache.save(this.locale, data.hash, data.translations);
            }
            // Pokud changed === false → cache je aktuální, nic neměníme

        } catch (error) {
            console.warn('[Translator] Fetch error:', error);
            // Pokud máme cache, to stačí
            // Jinak tiché selhání
        }

        this.ready = true;
    }

    /**
     * Přeloží klíč.
     *
     * @param domain  Doména překladu ('errors', 'ui', 'api', 'config')
     * @param key     Klíč v tečkové notaci ('http.400', 'form.required')
     * @param fallback Výchozí text pokud překlad neexistuje
     * @returns Přeložený text
     */
    t(domain: string, key: string, fallback?: string): string {
        return this.translations[domain]?.[key] ?? fallback ?? key;
    }

    /**
     * Přeloží klíč s interpolací parametrů.
     * Parametry ve formátu {:param} se nahradí hodnotami.
     *
     * @example
     * Translator.tp('errors', 'ai.timeout', { seconds: 30 })
     * // "Požadavek vypršel po 30 sekundách"
     */
    tp(domain: string, key: string, params: Record<string, string | number>, fallback?: string): string {
        let message = this.t(domain, key, fallback);

        for (const [param, value] of Object.entries(params)) {
            message = message.replace(`{:${param}}`, String(value));
        }

        return message;
    }

    /**
     * Vrátí zda je Translator připraven (překlady načteny).
     */
    isReady(): boolean {
        return this.ready;
    }

    /**
     * Vrátí aktuální locale.
     */
    getLocale(): string {
        return this.locale;
    }

    /**
     * Vyčistí cache a vynutí opětovné načtení při dalším bootu.
     */
    clearCache(): void {
        TranslationCache.clear();
    }

    /**
     * Čeká dokud Translator nebude připraven.
     * Užitečné pro kód který se spouští nezávisle na boot pipeline.
     */
    async whenReady(): Promise<void> {
        if (this.ready) return;
        if (this.bootPromise) return this.bootPromise;

        // Fallback: poll (nemělo by nastat)
        return new Promise(resolve => {
            const check = () => {
                if (this.ready) resolve();
                else setTimeout(check, 50);
            };
            check();
        });
    }

    // ─── Private ──────────────────────────────────────

    private buildUrl(endpoint: string, locale: string, domains: string[], hash?: string): string {
        const params = new URLSearchParams({
            locale,
            domains: domains.join(','),
        });

        if (hash) {
            params.set('hash', hash);
        }

        return `${endpoint}?${params.toString()}`;
    }

    private async fetchWithTimeout(url: string, timeout: number): Promise<Response> {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeout);

        try {
            return await fetch(url, {
                signal: controller.signal,
                credentials: 'same-origin',
            });
        } finally {
            clearTimeout(timer);
        }
    }
}

/** Globální singleton — importuje se odkudkoliv */
export const Translator = new TranslatorClass();
```

### 3.4. Integrace do App.init()

```typescript
// assets/ts/app.ts

import { Translator } from 'assets/ts/i18n';

export class App {
    // ...

    private readonly pipeline = [
        { name: "DOM",       step: () => this.initDom() },
        { name: "I18n",      step: () => this.initTranslator() },     // ← NOVÝ krok
        { name: "Core",      step: () => this.initCore() },
        { name: "Services",  step: () => this.initServices() },
        { name: "Features",  step: () => this.initFeatures() },
        { name: "Bindings",  step: () => this.bindEvents() },
    ];

    async init(): Promise<void> {
        for (const stage of this.pipeline) {
            await stage.step(); // await kvůli async i18n bootu
        }
    }

    private async initTranslator(): Promise<void> {
        const module = document.querySelector<HTMLElement>('.ai-module');
        const locale = module?.dataset.locale ?? 'cs';
        const translationUrl = module?.dataset.translationUrl ?? '/api/translations';

        await Translator.boot({
            locale,
            endpoint: translationUrl,
            domains: ['errors', 'ui'],
            timeout: 3000,
        });
    }
}
```

PHP strana dodá `data-locale` a `data-translation-url` na wrapper element:

```html
<div class="ai-module"
     data-api-url="/api/endpoint"
     data-locale="cs"
     data-translation-url="/api/translations">
```

### 3.5. Optimistic loading

Translator používá **optimistic strategii**:

1. Pokud je cache v localStorage → překlady jsou **okamžitě** dostupné (bez čekání na HTTP)
2. Na pozadí proběhne fetch pro ověření aktuálnosti
3. Pokud server vrátí nová data → cache se aktualizuje (projeví se při dalším načtení)
4. Pokud fetch selže → cache stačí

Tím je **první render nikdy blokován** čekáním na HTTP (kromě úplně prvního načtení, kdy cache neexistuje).

---

## 4. PHP implementace — změny oproti v1

### 4.1. Zachováno z v1

Následující třídy zůstávají **beze změny** (jsou stále platné):

- `Domain` enum
- `TranslationCatalog`
- `JsonFileLoader` + `TranslationLoaderInterface`
- `PlatformLoader`
- `Translator` hlavní fasáda (s drobnými úpravami)
- `DatabaseConnectionInterface` + `MysqliConnection`
- `NullAdapter`

### 4.2. Změny v MysqliAdapter

Přidán `hash` sloupec a metoda pro skupinový hash:

```php
<?php
namespace PlatformBridge\Translator\Adapter;

use PlatformBridge\Translator\Database\DatabaseConnectionInterface;
use PlatformBridge\Translator\Database\MysqliConnection;
use PlatformBridge\Translator\Database\TableProvisioner;
use PlatformBridge\Translator\Domain;

final class MysqliAdapter implements PlatformAdapterInterface
{
    public function __construct(
        private readonly DatabaseConnectionInterface $connection,
        private readonly string $tableName = 'pb_translations',
    ) {}

    /**
     * Factory — vytvoří adaptér z mysqli instance.
     * Volitelně zajistí existenci tabulky (auto-provisioning).
     */
    public static function fromMysqli(
        \mysqli $mysqli,
        string $tableName = 'pb_translations',
        bool $ensureTable = true,
    ): self {
        if ($ensureTable) {
            (new TableProvisioner($mysqli, $tableName))->ensure();
        }

        return new self(new MysqliConnection($mysqli), $tableName);
    }

    public function fetch(string $locale, Domain $domain): array
    {
        $sql = sprintf(
            'SELECT `key_path`, `value` FROM %s WHERE `locale` = ? AND `domain` = ?',
            $this->escapeIdentifier($this->tableName)
        );

        $rows = $this->connection->fetchAll($sql, [$locale, $domain->value], 'ss');

        $result = [];
        foreach ($rows as $row) {
            $result[$row['key_path']] = $row['value'];
        }

        return $result;
    }

    public function supports(): bool
    {
        return true;
    }

    public function availableLocales(): array
    {
        $sql = sprintf(
            'SELECT DISTINCT `locale` FROM %s',
            $this->escapeIdentifier($this->tableName)
        );

        return $this->connection->fetchColumn($sql);
    }

    private function escapeIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
```

### 4.3. Doplňky k Translator fasádě

```php
// Translator.php — nové metody:

/**
 * Vrátí VariableResolver svázaný s tímto translatorem.
 * Pro nahrazování {$domain.key} proměnných v textech.
 */
public function getVariableResolver(): VariableResolver
{
    return new VariableResolver($this);
}

/**
 * Vrátí TranslationCatalog (pro export endpointu).
 */
public function getCatalog(): TranslationCatalog
{
    return $this->catalog;
}
```

### 4.4. PlatformBridgeBuilder — rozšíření

```php
// PlatformBridgeBuilder.php — nové metody:

private ?\mysqli $mysqli = null;
private string $translationTable = 'pb_translations';

/**
 * Předá mysqli instanci pro překladový systém.
 * Tabulka 'pb_translations' bude automaticky vytvořena pokud neexistuje.
 *
 * @param \mysqli $mysqli Existující mysqli připojení
 * @param string $tableName Název tabulky (default: 'pb_translations')
 */
public function withMysqli(\mysqli $mysqli, string $tableName = 'pb_translations'): self
{
    $this->mysqli = $mysqli;
    $this->translationTable = $tableName;
    return $this;
}
```

### 4.5. PlatformBridge::boot()

```php
// PlatformBridge.php:

private Translator $translator;
private VariableResolver $variableResolver;

private function boot(): void
{
    $this->bootErrorHandler();
    $this->bootTranslator();        // ← NOVÝ — před bootConfig!
    $this->bootConfig();
    $this->bootTemplateEngine();
    $this->bootHandlers();
    $this->bootFormRenderer();
    $this->bootAssetManager();
    $this->bootSecurity();
}

private function bootTranslator(): void
{
    $adapter = null;

    // Pokud máme mysqli → použij MysqliAdapter (auto-provisioning tabulky)
    $mysqli = $this->config->getMysqli();
    if ($mysqli !== null) {
        $adapter = MysqliAdapter::fromMysqli(
            $mysqli,
            $this->config->getTranslationTable(),
            ensureTable: true
        );
    }

    $this->translator = Translator::create(
        locale: $this->config->getLocale(),
        langPath: $this->config->getPathResolver()->langPath(),
        adapter: $adapter,
    );

    $this->variableResolver = $this->translator->getVariableResolver();
}
```

---

## 5. Kompletní struktura souborů

```
src/PlatformBridge/
└── Translator/
    ├── Translator.php                     # Hlavní fasáda
    ├── TranslationCatalog.php             # Drží překlady pro locale
    ├── TranslationEndpoint.php            # HTTP handler pro frontend fetch
    ├── VariableResolver.php               # Nahrazuje {$domain.key} proměnné
    ├── Domain.php                         # Enum domén
    ├── Loader/
    │   ├── TranslationLoaderInterface.php
    │   ├── JsonFileLoader.php             # Statické JSON soubory
    │   └── PlatformLoader.php             # Přes DB adaptér
    ├── Adapter/
    │   ├── PlatformAdapterInterface.php
    │   ├── NullAdapter.php                # Standalone mód
    │   └── MysqliAdapter.php              # Vestavěný MySQL adaptér
    └── Database/
        ├── DatabaseConnectionInterface.php
        ├── MysqliConnection.php           # mysqli wrapper
        └── TableProvisioner.php           # Auto-create tabulky

resources/
└── lang/
    ├── cs/
    │   ├── errors.json
    │   ├── ui.json
    │   └── api.json
    └── en/
        ├── errors.json
        ├── ui.json
        └── api.json

assets/ts/
└── i18n/
    ├── Translator.ts                      # Globální Translator singleton
    ├── TranslationCache.ts                # localStorage cache s hash validací
    ├── types.ts
    └── index.ts                           # Re-export
```

---

## 6. Použití z pohledu vývojáře (consumer API)

### 6.1. Minimální setup (bez DB)

```php
$bridge = PlatformBridge::create()
    ->withLocale('cs')
    ->build();

// Překlady se berou pouze ze statických JSON souborů
```

### 6.2. S databází (doporučeno)

```php
$mysqli = new \mysqli('localhost', 'user', 'pass', 'my_database');

$bridge = PlatformBridge::create()
    ->withLocale('cs')
    ->withMysqli($mysqli)                // ← tabulka se auto-vytvoří
    ->build();

// Překlady: JSON → DB override → runtime override
```

### 6.3. S vlastním názvem tabulky

```php
$bridge = PlatformBridge::create()
    ->withLocale('cs')
    ->withMysqli($mysqli, 'my_translations')
    ->build();
```

### 6.4. PHP překlad

```php
// Jednoduché
$t->t('errors', 'http.400');                        // "Neplatný požadavek."
$t->t('ui', 'form.required');                        // "Toto pole je povinné"

// S parametry
$t->t('errors', 'ai.timeout', ['seconds' => 30]);   // "Požadavek vypršel po 30 sekundách"

// Proměnné v JSON
$block = $configManager->getBlock('tone');
$block = $variableResolver->resolveArray($block);
// block['label'] = "{$config.blocks.tone.label|Tone}" → "Tón komunikace"
```

### 6.5. TS překlad

```typescript
import { Translator } from 'assets/ts/i18n';

// Jednoduché
Translator.t('errors', 'http.400');                         // "Neplatný požadavek."
Translator.t('ui', 'notification.close');                   // "Zavřít"

// S parametry
Translator.tp('errors', 'ai.timeout', { seconds: 30 });    // "Požadavek vypršel po 30 sekundách"

// S fallbackem
Translator.t('ui', 'neexistujici.klic', 'Default text');   // "Default text"
```

---

## 7. Cache flow — detailní diagram

```
┌─────────────── PRVNÍ NAČTENÍ ────────────────┐
│                                                │
│  localStorage: prázdný                         │
│  → Fetch: GET /api/translations?locale=cs      │
│  ← 200: { changed: true, hash: "a1b2", ... }  │
│  → Ulož do localStorage                        │
│  → Translator ready ✓                          │
│                                                │
└────────────────────────────────────────────────┘

┌─────────── DALŠÍ NAČTENÍ (cache hit) ─────────┐
│                                                │
│  localStorage: { hash: "a1b2", ... }           │
│  → Translator ready ✓ (okamžitě z cache!)      │
│  → Fetch: GET /api/translations?locale=cs      │
│           &hash=a1b2                           │
│  ← 200: { changed: false }                    │
│  → Cache stále platná ✓                        │
│                                                │
└────────────────────────────────────────────────┘

┌─────── DALŠÍ NAČTENÍ (cache invalidace) ──────┐
│                                                │
│  localStorage: { hash: "a1b2", ... }           │
│  → Translator ready ✓ (okamžitě z cache!)      │
│  → Fetch: GET /api/translations?locale=cs      │
│           &hash=a1b2                           │
│  ← 200: { changed: true, hash: "c3d4", ... }  │
│  → Aktualizuj localStorage                     │
│  → Nové překlady platné od příštího reload ✓   │
│                                                │
└────────────────────────────────────────────────┘

┌─────── FETCH SELŽE (offline / timeout) ───────┐
│                                                │
│  localStorage: { hash: "a1b2", ... }           │
│  → Translator ready ✓ (okamžitě z cache!)      │
│  → Fetch: selhání (timeout, network error)     │
│  → Cache stačí → žádný dopad na UX ✓           │
│                                                │
└────────────────────────────────────────────────┘
```

---

## 8. PathResolver — nová metoda `langPath()`

```php
// PathResolver.php — přidat:

/**
 * Vrací absolutní cestu do adresáře s překladovými soubory (lang).
 * Např. .../platformbridge/resources/lang
 */
public function langPath(): string
{
    return $this->packageRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang';
}
```

---

## 9. Pořadí implementace

### Fáze 1: PHP základ (bez DB)
1. `Domain` enum
2. `TranslationCatalog`
3. `JsonFileLoader` + `TranslationLoaderInterface`
4. `Translator` fasáda
5. `VariableResolver`
6. `resources/lang/cs/` a `en/` JSON soubory
7. `PathResolver::langPath()`
8. `bootTranslator()` v `PlatformBridge::boot()`

### Fáze 2: DB vrstva
1. `DatabaseConnectionInterface` + `MysqliConnection`
2. `TableProvisioner` (auto-create tabulky)
3. `PlatformAdapterInterface` + `NullAdapter`
4. `MysqliAdapter` (s `ensureTable`)
5. `PlatformLoader`
6. `withMysqli()` v Builderu
7. Krok `translations` v Installeru

### Fáze 3: Frontend Translator
1. `TranslationEndpoint.php` (PHP endpoint)
2. `TranslationCache.ts` (localStorage)
3. `Translator.ts` (globální třída)
4. Integrace do `App.init()` pipeline
5. `data-locale` + `data-translation-url` na wrapper element

### Fáze 4: Migrace hardcoded textů
1. PHP: `AiException`, `ErrorRenderer`, `NestedResult.tpl`
2. TS: `ErrorHandler`, `MessageRenderer`, `FormValidator`
3. JSON: proměnné `{$config.blocks...}` v `blocks.json`

---

## 10. Shrnutí klíčových rozhodnutí

| Rozhodnutí | Volba | Důvod |
|---|---|---|
| Existující tabulky | **Ne** — jedna vlastní `pb_translations` | Adaptovat se na cizí schema je příliš složité |
| DB | **Pouze mysqli** | Jediný DB driver v projektu |
| Auto-create tabulky | **Ano** — `TableProvisioner` | Při instalaci i lazy při bootu |
| Frontend překlady | **Fetch API** + localStorage cache | Žádný inline script, čistý bootstrap |
| Cache invalidace | **Hash systém** (crc32b) | Server/klient porovnají hash → stáhne se jen když se liší |
| Optimistic loading | **Ano** — cache = okamžitě ready | First render nikdy čeká na HTTP |
| Config texty | **Proměnné** `{$domain.key\|fallback}` | Generické, budoucí klíče fungují automaticky |
| TS Translator | **Globální třída** v pipeline | Ne vanilla script, integrovaný do App |
| Interpolace | `{:param}` (PHP i TS) | Jednoduché, konzistentní |
| Proměnná syntaxe | `{$domain.key\|fallback}` (PHP resolve) | Odlišné od interpolace, jasné oddělení |
