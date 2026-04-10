# PlatformBridge – Návrh translation systému

> Verze: 1.1 | Datum: 2026-03-31
> Status: NÁVRH (aktualizováno)

---

## 1. Analýza současného stavu

### 1.1. Identifikované typy překladů

V projektu existují **4 odlišné domény** překládaných textů:

| Doména     | Kde se používá                          | Příklady                                            | Prostředí |
|------------|----------------------------------------|-----------------------------------------------------|-----------|
| `errors`   | Chybové hlášky, výjimky                | "Neplatný požadavek", "Časový limit vypršel"         | PHP + TS  |
| `ui`       | Labely, tlačítka, notifikace           | "Toto pole je povinné", "Zavřít", "Úspěch"          | PHP + TS  |
| `api`      | Klíče z AI odpovědí                    | "Subject", "Preheader" (+ budoucí)                   | PHP → TS  |
| `config`   | JSON konfigurace (blocks, generators)  | "Formal", "Goal", "Emoji in subject"                 | PHP (DB only) |

### 1.2. Kde jsou hardcoded texty dnes

**PHP:**
- `AiException.php` — "Neplatný request:", "Chyba připojení:", "API chyba:" atd.
- `ErrorRenderer.php` — "Něco se pokazilo", "Problém byl zaznamenán..."
- `AiResponse.php` — "Neznámá chyba"
- `NestedResult.tpl` — `{$key}` renderuje API klíče přímo (Subject, Preheader)

**TypeScript:**
- `ErrorHandler.ts` — `HTTP_MESSAGES`, `CODE_TITLES`, `CODE_USER_MESSAGES`, `TYPE_TITLES`
- `MessageRenderer.ts` — `DEFAULT_TITLES` ('Chyba', 'Úspěch', ...), "Zavřít"
- `FormValidator.ts` — 'Toto pole je povinné'

**JSON:**
- `blocks.json` — label, tooltip, options (mix CZ/EN: "Tón", "Goal", "Friendly")
- `generators.json` — "Email Subjects (Advanced)"

> **DŮLEŽITÉ:** Bloky a generátory jsou dynamické per-uživatel/platforma (desítky až stovky).
> Statické JSON překlady pro doménu `config` NEDÁVAJÍ SMYSL — překlady musí jít z DB.

### 1.3. Existující infrastruktura

- `PlatformBridgeBuilder::withLocale()` — už existuje, předává locale do configu
- `PlatformBridgeConfig::$locale` — uložen, ale nepoužíván (getter zakomentovaný)
- `Parser.php` — template tag `{_tran k='key' d='default' l='lang'}` již implementován
- `Translator::fetchTranslations()` — referencováno v Parseru, ale třída neexistuje
- UML diagram — plánovaná třída `Translator` s `setLocale()`, `translate()`, `t()`

---

## 2. Architektura

### 2.1. Princip: vrstvy překladu s prioritou

```
┌─────────────────────────────────────────────┐
│  3. Runtime overrides (per-request)          │  ← nejvyšší priorita
├─────────────────────────────────────────────┤
│  2. Platform DB (adaptér)                    │  ← přepisujeDefaultTranslations
├─────────────────────────────────────────────┤
│  1. Statické JSON soubory (resources/lang/)  │  ← výchozí překlady v balíčku
└─────────────────────────────────────────────┘
```

Každá vrstva může přepsat klíče z nižší vrstvy. Pokud klíč není nalezen v žádné vrstvě, použije se fallback locale (en).

### 2.2. Struktura souborů

```
src/PlatformBridge/
└── Translator/
    ├── Translator.php                     # Hlavní fasáda
    ├── TranslationCatalog.php             # Drží načtené překlady pro locale
    ├── Domain.php                         # Enum překladových domén
    ├── Loader/
    │   ├── TranslationLoaderInterface.php # Kontrakt pro loader
    │   ├── JsonFileLoader.php             # Načítá z resources/lang/{locale}/
    │   └── PlatformLoader.php             # Načítá z DB přes adaptér
    ├── Adapter/
    │   ├── PlatformAdapterInterface.php   # Kontrakt pro platformu
    │   ├── NullAdapter.php                # Standalone mód (bez DB)
    │   └── MysqliAdapter.php             # Vestavěný MySQL adaptér (mysqli)
    ├── Database/
    │   ├── DatabaseConnectionInterface.php # Abstrakce nad DB připojením
    │   └── MysqliConnection.php           # mysqli implementace
    └── Cache/
        └── TranslationCache.php           # Volitelné: cache s invalidací

resources/
└── lang/                                  # Výchozí překlady (součást balíčku)
    ├── cs/
    │   ├── errors.json
    │   ├── ui.json
    │   └── api.json
    └── en/
        ├── errors.json
        ├── ui.json
        └── api.json
    # ⚠ config.json ODSTRANĚN — config překlady jdou výhradně z DB

assets/ts/
└── i18n/
    ├── Translator.ts                      # TS translační fasáda
    ├── TranslationStore.ts                # In-memory store
    ├── types.ts                           # Typy
    └── index.ts
```

### 2.3. Tok dat (flow)

```
                    ┌──────────────────┐
                    │  PlatformBridge  │
                    │    ::boot()      │
                    └────────┬─────────┘
                             │
                    ┌────────▼─────────┐
                    │ bootTranslator() │
                    │  locale = 'cs'   │
                    └────────┬─────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
     ┌────────────┐  ┌────────────┐  ┌──────────┐
     │ JsonFile   │  │ Platform   │  │ Runtime  │
     │ Loader     │  │ Loader     │  │ Override │
     │ (lang/cs/) │  │ (DB adapt) │  │ (array)  │
     └─────┬──────┘  └─────┬──────┘  └────┬─────┘
           │               │               │
           └───────────────┼───────────────┘
                           ▼
                 ┌───────────────────┐
                 │ TranslationCatalog│
                 │  (merged result)  │
                 └────────┬──────────┘
                          │
          ┌───────────────┼───────────────┐
          ▼               ▼               ▼
   ┌────────────┐  ┌────────────┐  ┌──────────────┐
   │ PHP code   │  │ Templates  │  │ TS frontend  │
   │ t('key')   │  │ {_tran}    │  │ (injected)   │
   └────────────┘  └────────────┘  └──────────────┘
```

---

## 3. PHP implementace

### 3.1. Domain enum

```php
<?php
namespace PlatformBridge\Translator;

enum Domain: string
{
    case Errors = 'errors';
    case UI     = 'ui';
    case Api    = 'api';
    case Config = 'config';
}
```

### 3.2. PlatformAdapterInterface

```php
<?php
namespace PlatformBridge\Translator\Adapter;

use PlatformBridge\Translator\Domain;

/**
 * Kontrakt pro platformový adaptér překladů.
 *
 * Každá platforma (CMS, custom app, ...) implementuje tento interface
 * a dodává překlady ze své DB struktury.
 */
interface PlatformAdapterInterface
{
    /**
     * Načte překlady pro daný locale a doménu z platformového úložiště.
     *
     * @return array<string, string> ['klíč' => 'přeložený text', ...]
     */
    public function fetch(string $locale, Domain $domain): array;

    /**
     * Podporuje platforma ukládání překladů?
     * Pokud false, loader tuto vrstvu přeskočí.
     */
    public function supports(): bool;

    /**
     * Vrátí seznam dostupných locale kódů v platformě.
     *
     * @return string[] ['cs', 'en', 'de', ...]
     */
    public function availableLocales(): array;
}
```

### 3.3. NullAdapter (standalone mód)

```php
<?php
namespace PlatformBridge\Translator\Adapter;

use PlatformBridge\Translator\Domain;

/**
 * Nulový adaptér pro standalone režim bez připojení k databázi.
 * Vrací vždy prázdné pole — překlady se berou pouze ze statických souborů.
 */
final class NullAdapter implements PlatformAdapterInterface
{
    public function fetch(string $locale, Domain $domain): array
    {
        return [];
    }

    public function supports(): bool
    {
        return false;
    }

    public function availableLocales(): array
    {
        return [];
    }
}
```

### 3.4. DatabaseConnectionInterface + MysqliConnection

> **Zásada: žádné PDO.** Aplikace používá `mysqli`. Nad ním je tenká abstrakce,
> aby se dal případně nahradit jiným driverem.

```php
<?php
namespace PlatformBridge\Translator\Database;

/**
 * Minimální abstrakce nad databázovým připojením.
 * Používá se pouze pro překladový systém — není to full ORM.
 */
interface DatabaseConnectionInterface
{
    /**
     * Vykoná prepared statement a vrátí pole výsledků.
     *
     * @param string $sql       SQL dotaz s ? placeholdery
     * @param array  $params    Parametry pro bind
     * @param string $types     mysqli bind_param types string (např. 'ss')
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = [], string $types = ''): array;

    /**
     * Vykoná prepared statement a vrátí jeden sloupec ze všech řádků.
     *
     * @return array<int, mixed>
     */
    public function fetchColumn(string $sql, array $params = [], string $types = '', int $column = 0): array;
}
```

```php
<?php
namespace PlatformBridge\Translator\Database;

/**
 * mysqli implementace DatabaseConnectionInterface.
 *
 * Obaluje existující mysqli instanci tenkou vrstvou pro prepared statements.
 * Uživatel předá svou existující mysqli instanci — žádné nové připojení se nevytváří.
 *
 * @example
 * ```php
 * $mysqli = new \mysqli('localhost', 'user', 'pass', 'db');
 * $connection = new MysqliConnection($mysqli);
 * ```
 */
final class MysqliConnection implements DatabaseConnectionInterface
{
    public function __construct(
        private readonly \mysqli $mysqli
    ) {}

    public function fetchAll(string $sql, array $params = [], string $types = ''): array
    {
        $stmt = $this->mysqli->prepare($sql);

        if ($stmt === false) {
            throw new \RuntimeException('MySQL prepare failed: ' . $this->mysqli->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) {
            $stmt->close();
            throw new \RuntimeException('MySQL query failed: ' . $stmt->error);
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    public function fetchColumn(string $sql, array $params = [], string $types = '', int $column = 0): array
    {
        $rows = $this->fetchAll($sql, $params, $types);

        if (empty($rows)) {
            return [];
        }

        $keys = array_keys($rows[0]);
        $col = $keys[$column] ?? $keys[0];

        return array_column($rows, $col);
    }
}
```

### 3.5. MysqliAdapter (vestavěný MySQL adaptér)

> Uživatel nemusí psát vlastní adaptér pro MySQL — stačí předat `mysqli` instanci.
> Pro jiné DB (PostgreSQL, API, Redis...) implementuje `PlatformAdapterInterface`.

```php
<?php
namespace PlatformBridge\Translator\Adapter;

use PlatformBridge\Translator\Database\DatabaseConnectionInterface;
use PlatformBridge\Translator\Database\MysqliConnection;
use PlatformBridge\Translator\Domain;

/**
 * Vestavěný MySQL adaptér pro překlady z databáze.
 *
 * Očekává tabulku s touto strukturou (nebo kompatibilní):
 *
 *   CREATE TABLE pb_translations (
 *       id INT AUTO_INCREMENT PRIMARY KEY,
 *       locale VARCHAR(5) NOT NULL,
 *       domain VARCHAR(20) NOT NULL,
 *       key_path VARCHAR(255) NOT NULL,
 *       value TEXT NOT NULL,
 *       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *       UNIQUE KEY unique_translation (locale, domain, key_path)
 *   );
 *
 * Název tabulky je konfigurovatelný.
 *
 * @example
 * ```php
 * $adapter = MysqliAdapter::fromMysqli($mysqli);
 * // nebo s vlastním názvem tabulky:
 * $adapter = MysqliAdapter::fromMysqli($mysqli, 'my_translations');
 * ```
 */
final class MysqliAdapter implements PlatformAdapterInterface
{
    public function __construct(
        private readonly DatabaseConnectionInterface $connection,
        private readonly string $tableName = 'pb_translations',
    ) {}

    /**
     * Factory — vytvoří adaptér přímo z mysqli instance.
     */
    public static function fromMysqli(\mysqli $mysqli, string $tableName = 'pb_translations'): self
    {
        return new self(new MysqliConnection($mysqli), $tableName);
    }

    public function fetch(string $locale, Domain $domain): array
    {
        $sql = sprintf(
            'SELECT key_path, value FROM %s WHERE locale = ? AND domain = ?',
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
            'SELECT DISTINCT locale FROM %s',
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

### 3.6. TranslationLoaderInterface + JsonFileLoader

```php
<?php
namespace PlatformBridge\Translator\Loader;

use PlatformBridge\Translator\Domain;

interface TranslationLoaderInterface
{
    /**
     * @return array<string, string>
     */
    public function load(string $locale, Domain $domain): array;
}
```

```php
<?php
namespace PlatformBridge\Translator\Loader;

use PlatformBridge\Translator\Domain;

/**
 * Načítá výchozí překlady ze statických JSON souborů.
 * Cesta: {basePath}/{locale}/{domain}.json
 */
final class JsonFileLoader implements TranslationLoaderInterface
{
    public function __construct(
        private readonly string $basePath  // resources/lang
    ) {}

    public function load(string $locale, Domain $domain): array
    {
        $file = sprintf('%s/%s/%s.json', $this->basePath, $locale, $domain->value);

        if (!is_file($file)) {
            return [];
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        return is_array($data) ? $this->flatten($data) : [];
    }

    /**
     * Zploští vnořené pole do tečkové notace.
     * ['http' => ['400' => 'Bad']] → ['http.400' => 'Bad']
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}.{$key}" : (string)$key;

            if (is_array($value)) {
                $result += $this->flatten($value, $fullKey);
            } else {
                $result[$fullKey] = (string)$value;
            }
        }

        return $result;
    }
}
```

### 3.7. PlatformLoader

```php
<?php
namespace PlatformBridge\Translator\Loader;

use PlatformBridge\Translator\Adapter\PlatformAdapterInterface;
use PlatformBridge\Translator\Domain;

/**
 * Deleguje načítání překladů na platformový adaptér (DB).
 */
final class PlatformLoader implements TranslationLoaderInterface
{
    public function __construct(
        private readonly PlatformAdapterInterface $adapter
    ) {}

    public function load(string $locale, Domain $domain): array
    {
        if (!$this->adapter->supports()) {
            return [];
        }

        return $this->adapter->fetch($locale, $domain);
    }
}
```

### 3.8. TranslationCatalog

```php
<?php
namespace PlatformBridge\Translator;

/**
 * Drží načtené překlady pro jeden locale.
 * Podporuje lookup s doménou a fallback.
 */
final class TranslationCatalog
{
    /** @var array<string, array<string, string>> domain => [key => value] */
    private array $messages = [];

    private ?self $fallback = null;

    public function __construct(
        private readonly string $locale
    ) {}

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setFallback(self $fallback): void
    {
        $this->fallback = $fallback;
    }

    /**
     * Nastaví překlady pro doménu (merguje s existujícími).
     */
    public function addMessages(Domain $domain, array $messages): void
    {
        $existing = $this->messages[$domain->value] ?? [];
        $this->messages[$domain->value] = array_merge($existing, $messages);
    }

    /**
     * Hledá překlad: doména → fallback catalog.
     */
    public function get(Domain $domain, string $key, ?string $default = null): string
    {
        // Hledej v aktuálním locale
        if (isset($this->messages[$domain->value][$key])) {
            return $this->messages[$domain->value][$key];
        }

        // Fallback na jiný locale (typicky en)
        if ($this->fallback !== null) {
            return $this->fallback->get($domain, $key, $default);
        }

        // Klíč nenalezen → vrať default nebo klíč samotný
        return $default ?? $key;
    }

    /**
     * Vrátí všechny překlady pro doménu (pro export do JS).
     *
     * @return array<string, string>
     */
    public function allForDomain(Domain $domain): array
    {
        $messages = $this->messages[$domain->value] ?? [];

        if ($this->fallback !== null) {
            $fallbackMessages = $this->fallback->allForDomain($domain);
            $messages = array_merge($fallbackMessages, $messages);
        }

        return $messages;
    }
}
```

### 3.9. Translator (hlavní fasáda)

```php
<?php
namespace PlatformBridge\Translator;

use PlatformBridge\Translator\Loader\TranslationLoaderInterface;
use PlatformBridge\Translator\Adapter\PlatformAdapterInterface;
use PlatformBridge\Translator\Adapter\NullAdapter;
use PlatformBridge\Translator\Loader\JsonFileLoader;
use PlatformBridge\Translator\Loader\PlatformLoader;

/**
 * Hlavní fasáda překladového systému.
 *
 * Kombinuje statické soubory (JSON) s platformovými překlady (DB).
 * Podporuje domény, fallback locale a runtime overrides.
 *
 * @example
 * ```php
 * $t = Translator::create('cs', $paths->langPath());
 *
 * // Základní překlad
 * echo $t->t('errors', 'http.400');                    // "Neplatný požadavek."
 * echo $t->t('ui', 'form.required');                    // "Toto pole je povinné"
 * echo $t->t('api', 'keys.subject');                    // "Předmět"
 * echo $t->t('config', 'blocks.tone.label');            // "Tón komunikace"
 *
 * // S parametry
 * echo $t->t('errors', 'timeout', ['seconds' => 30]);  // "Požadavek vypršel po 30 sekundách"
 *
 * // Export pro frontend
 * $jsBundle = $t->exportForFrontend(['errors', 'ui']); // JSON string
 * ```
 */
final class Translator
{
    private TranslationCatalog $catalog;
    private TranslationCatalog $fallbackCatalog;

    /** @var TranslationLoaderInterface[] */
    private array $loaders;

    /** Singleton pro template engine (zpětná kompatibilita s Parser.php) */
    private static ?self $instance = null;

    public function __construct(
        private readonly string $locale,
        private readonly string $fallbackLocale = 'en',
        array $loaders = [],
    ) {
        $this->loaders = $loaders;
        $this->catalog = new TranslationCatalog($locale);
        $this->fallbackCatalog = new TranslationCatalog($fallbackLocale);

        if ($locale !== $fallbackLocale) {
            $this->catalog->setFallback($this->fallbackCatalog);
        }

        $this->loadAll();

        self::$instance = $this;
    }

    /**
     * Factory pro rychlé vytvoření s JsonFileLoader + volitelným platformovým adaptérem.
     */
    public static function create(
        string $locale,
        string $langPath,
        ?PlatformAdapterInterface $adapter = null,
        string $fallbackLocale = 'en',
    ): self {
        $loaders = [
            new JsonFileLoader($langPath),
        ];

        if ($adapter !== null) {
            $loaders[] = new PlatformLoader($adapter);
        }

        return new self($locale, $fallbackLocale, $loaders);
    }

    /**
     * Přeloží klíč s volitelnou interpolací parametrů.
     *
     * @param string $domain  Doména ('errors', 'ui', 'api', 'config')
     * @param string $key     Klíč překladu (tečková notace)
     * @param array  $params  Parametry pro interpolaci {:param}
     * @param string|null $default Výchozí hodnota pokud klíč neexistuje
     */
    public function t(string $domain, string $key, array $params = [], ?string $default = null): string
    {
        $domainEnum = Domain::from($domain);
        $message = $this->catalog->get($domainEnum, $key, $default);

        if (!empty($params)) {
            $message = $this->interpolate($message, $params);
        }

        return $message;
    }

    /**
     * Alias pro t() — plný název.
     */
    public function translate(string $domain, string $key, array $params = [], ?string $default = null): string
    {
        return $this->t($domain, $key, $params, $default);
    }

    /**
     * Přidá runtime override pro danou doménu.
     * Přepíše existující klíče.
     */
    public function override(string $domain, array $messages): void
    {
        $this->catalog->addMessages(Domain::from($domain), $messages);
    }

    /**
     * Export překladů pro frontend (JSON string).
     * Obsahuje všechny klíče pro zadané domény.
     *
     * @param string[] $domains Které domény exportovat (default: errors + ui)
     */
    public function exportForFrontend(array $domains = ['errors', 'ui']): string
    {
        $export = [];

        foreach ($domains as $domain) {
            $export[$domain] = $this->catalog->allForDomain(Domain::from($domain));
        }

        return json_encode($export, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Vrátí aktuální locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    // ─── Zpětná kompatibilita s template Parserem ─────

    /**
     * Statický přístup pro Parser.php ({_tran} tag).
     * Vyžaduje, aby Translator byl inicializován přes boot.
     */
    public static function fetchTranslations(string $key, ?string $lang, string $default): string
    {
        if (self::$instance === null) {
            return $default;
        }

        // Pokud klíč začíná doménou (errors.xxx), rozděl
        $parts = explode('.', $key, 2);
        if (count($parts) === 2 && Domain::tryFrom($parts[0]) !== null) {
            return self::$instance->t($parts[0], $parts[1], [], $default);
        }

        // Fallback: hledej ve všech doménách
        foreach (Domain::cases() as $domain) {
            $result = self::$instance->catalog->get($domain, $key);
            if ($result !== $key) {
                return $result;
            }
        }

        return $default;
    }

    // ─── Private ──────────────────────────────────────

    private function loadAll(): void
    {
        foreach (Domain::cases() as $domain) {
            foreach ($this->loaders as $loader) {
                // Načti pro aktuální locale
                $messages = $loader->load($this->locale, $domain);
                $this->catalog->addMessages($domain, $messages);

                // Načti pro fallback locale (pokud se liší)
                if ($this->locale !== $this->fallbackLocale) {
                    $fallbackMessages = $loader->load($this->fallbackLocale, $domain);
                    $this->fallbackCatalog->addMessages($domain, $fallbackMessages);
                }
            }
        }
    }

    /**
     * Nahradí {:param} placeholdery v textu.
     */
    private function interpolate(string $message, array $params): string
    {
        $replacements = [];

        foreach ($params as $key => $value) {
            $replacements['{:' . $key . '}'] = (string)$value;
        }

        return strtr($message, $replacements);
    }
}
```

---

## 4. Výchozí překladové soubory

### 4.1. `resources/lang/cs/errors.json`

```json
{
    "http": {
        "400": "Neplatný požadavek.",
        "401": "Neautorizovaný přístup.",
        "403": "Přístup zamítnut — neplatný bezpečnostní podpis.",
        "404": "API endpoint nenalezen.",
        "405": "Nepodporovaná HTTP metoda.",
        "408": "Požadavek vypršel.",
        "422": "Chyba validace vstupních dat.",
        "429": "Příliš mnoho požadavků. Zkuste to později.",
        "500": "Interní chyba serveru.",
        "502": "Server dočasně nedostupný.",
        "503": "Služba je dočasně nedostupná.",
        "504": "Požadavek vypršel — server neodpověděl včas."
    },
    "ai": {
        "invalid_request": "Neplatný request: {:reason}",
        "connection_failed": "Chyba připojení: {:error}",
        "timeout": "Požadavek vypršel po {:seconds} sekundách",
        "invalid_response": "Neplatná odpověď: {:reason}",
        "api_error": "API chyba: {:message}",
        "unknown": "Neznámá chyba"
    },
    "code_titles": {
        "1001": "Neplatný požadavek",
        "1002": "Chyba validace",
        "1003": "Chyba připojení",
        "1004": "Časový limit vypršel",
        "1005": "Neplatná odpověď",
        "1006": "Chyba API",
        "2001": "Bezpečnostní chyba",
        "2002": "Token vypršel",
        "2003": "Chybějící token",
        "3001": "Neplatné výstupní data",
        "3002": "Neplatné výstupní data",
        "3003": "Neplatné výstupní data"
    },
    "code_messages": {
        "1001": "Požadavek obsahuje neplatná data.",
        "1002": "Vstupní data neprošla validací.",
        "1003": "Nelze se připojit k AI poskytovateli.",
        "1004": "Požadavek na AI poskytovatele vypršel.",
        "1005": "AI poskytovatel vrátil neplatnou odpověď.",
        "1006": "AI poskytovatel vrátil chybu.",
        "2001": "Neplatný bezpečnostní podpis požadavku.",
        "2002": "Platnost bezpečnostního tokenu vypršela. Obnovte stránku.",
        "2003": "Požadavek neobsahuje bezpečnostní token.",
        "3001": "Překročena maximální hloubka JSON.",
        "3002": "Neplatný formát JSON.",
        "3003": "Neplatné UTF-8 znaky ve výstupu."
    },
    "type_titles": {
        "network": "Chyba sítě",
        "http": "Chyba serveru",
        "api": "Chyba API",
        "validation": "Chyba validace",
        "parse": "Chyba zpracování",
        "timeout": "Časový limit",
        "dom": "Chyba aplikace",
        "unknown": "Neočekávaná chyba"
    },
    "generic": {
        "title": "Chyba",
        "something_wrong": "Něco se pokazilo",
        "contact_admin": "Problém byl zaznamenán. Pokud potíže přetrvávají, kontaktuj administrátora."
    }
}
```

### 4.2. `resources/lang/cs/ui.json`

```json
{
    "notification": {
        "error": "Chyba",
        "success": "Úspěch",
        "warning": "Varování",
        "info": "Informace",
        "close": "Zavřít"
    },
    "form": {
        "required": "Toto pole je povinné",
        "submit": "Odeslat",
        "generating": "Generuji..."
    },
    "result": {
        "title": "Odpověď",
        "copy": "Kopírovat",
        "use": "Použít",
        "repeat": "Vygenerovat znovu"
    }
}
```

### 4.3. `resources/lang/cs/api.json`

```json
{
    "keys": {
        "subject": "Předmět",
        "preheader": "Preheader"
    }
}
```

### ~~4.4. `resources/lang/cs/config.json`~~ — ODSTRANĚNO

> **Config překlady se neukládají do statických JSON souborů.**
> Bloky a generátory jsou dynamické (desítky až stovky, per-uživatel).
> Překlady config textů jdou **výhradně z DB** přes `MysqliAdapter` / `PlatformAdapterInterface`.
>
> Viz sekce 8 — jak se config texty překládají z DB.

### 4.5. `resources/lang/en/errors.json` (fallback — ukázka)

```json
{
    "http": {
        "400": "Bad request.",
        "401": "Unauthorized access.",
        "403": "Access denied — invalid security signature.",
        "404": "API endpoint not found.",
        "500": "Internal server error."
    },
    "ai": {
        "invalid_request": "Invalid request: {:reason}",
        "connection_failed": "Connection error: {:error}",
        "timeout": "Request timed out after {:seconds} seconds",
        "invalid_response": "Invalid response: {:reason}",
        "api_error": "API error: {:message}",
        "unknown": "Unknown error"
    },
    "generic": {
        "title": "Error",
        "something_wrong": "Something went wrong",
        "contact_admin": "The problem has been logged. If the issue persists, contact the administrator."
    }
}
```

---

## 5. Integrace do existujícího kódu

### 5.1. PlatformBridge — boot

```php
// PlatformBridge.php
use PlatformBridge\Translator\Translator;

private Translator $translator;

private function boot(): void
{
    $this->bootErrorHandler();
    $this->bootTranslator();    // ← NOVÝ krok — musí být PŘED bootConfig
    $this->bootConfig();
    $this->bootTemplateEngine();
    $this->bootHandlers();
    $this->bootFormRenderer();
    $this->bootAssetManager();
    $this->bootSecurity();
}

private function bootTranslator(): void
{
    $adapter = $this->config->getPlatformAdapter(); // null = NullAdapter

    $this->translator = Translator::create(
        locale: $this->config->getLocale(),
        langPath: $this->config->getPathResolver()->langPath(),
        adapter: $adapter,
    );
}
```

### 5.2. PlatformBridgeBuilder — adapter injection

```php
// PlatformBridgeBuilder.php
use PlatformBridge\Translator\Adapter\PlatformAdapterInterface;

private ?PlatformAdapterInterface $translationAdapter = null;

/**
 * Nastaví platformový adaptér pro překlady z databáze.
 */
public function withTranslationAdapter(PlatformAdapterInterface $adapter): self
{
    $this->translationAdapter = $adapter;
    return $this;
}
```

### 5.3. AiException — s translatorem

```php
// Před:
public static function timeout(int $seconds, array $context = []): self
{
    return new self("Požadavek vypršel po {$seconds} sekundách", ...);
}

// Po:
public static function timeout(int $seconds, array $context = []): self
{
    $message = Translator::fetchTranslations(
        'errors.ai.timeout', null,
        "Request timed out after {$seconds} seconds"
    );
    // Interpolace parametrů:
    $message = str_replace('{:seconds}', (string)$seconds, $message);
    return new self($message, self::ERROR_TIMEOUT, null, $context);
}
```

### 5.4. ErrorHandler.ts — s překladovým store

```typescript
// Před:
const HTTP_MESSAGES: Record<number, string> = {
    400: 'Neplatný požadavek.',
    // ...
};

// Po:
import { Translator } from 'assets/ts/i18n';

// HTTP_MESSAGES se načítají z Translator store
const httpMessage = (code: number): string =>
    Translator.t('errors', `http.${code}`, `HTTP Error ${code}`);
```

### 5.5. blocks.json — rozlišení anglický zdroj vs. překlad

`blocks.json` zůstane **anglický** (jako referenční/výchozí). Překlady se resolví za běhu:

```php
// V ConfigResolver nebo FormRenderer:
// Když čteme block z JSON:
$block = $this->configManager->getBlock('tone');
$label = $this->translator->t('config', "blocks.tone.label", [], $block['label']);
//                                                                   ↑ fallback z JSON
```

Tím se zachová zpětná kompatibilita — pokud překlad neexistuje, použije se text z JSON.

### 5.6. NestedResult.tpl — překlad API klíčů

```smarty
{* Před: *}
<h2 class="pb-result__label">{$key}</h2>

{* Po: *}
<h2 class="pb-result__label">{_tran k='api.keys.{$key}' d='{$key}'}</h2>
```

Šablonový parser `{_tran}` už existuje — stačí ho propojit s novým Translatorem.

---

## 6. TypeScript integrace

### 6.1. Jak dostat překlady do frontendu

PHP vygeneruje JSON bundle a vloží ho do stránky při renderování:

```php
// FormRenderer nebo AssetManager:
public function renderTranslationScript(): string
{
    $json = $this->translator->exportForFrontend(['errors', 'ui']);
    return sprintf('<script>window.__PB_TRANSLATIONS__ = %s;</script>', $json);
}
```

### 6.2. TS Translator

```typescript
// assets/ts/i18n/Translator.ts

export type TranslationMap = Record<string, Record<string, string>>;

class TranslatorClass {
    private translations: TranslationMap = {};
    private locale: string = 'en';

    /**
     * Inicializace z window.__PB_TRANSLATIONS__ (PHP inject)
     */
    init(locale: string): void {
        this.locale = locale;
        this.translations = (window as any).__PB_TRANSLATIONS__ ?? {};
    }

    /**
     * Přeloží klíč.
     * @example Translator.t('errors', 'http.400')
     * @example Translator.t('ui', 'form.required', 'This field is required')
     */
    t(domain: string, key: string, fallback?: string): string {
        return this.translations[domain]?.[key] ?? fallback ?? key;
    }

    /**
     * Přeloží klíč s parametry.
     * @example Translator.tp('errors', 'ai.timeout', { seconds: 30 })
     */
    tp(domain: string, key: string, params: Record<string, string | number>, fallback?: string): string {
        let message = this.t(domain, key, fallback);

        for (const [param, value] of Object.entries(params)) {
            message = message.replace(`{:${param}}`, String(value));
        }

        return message;
    }
}

/** Singleton instance */
export const Translator = new TranslatorClass();
```

### 6.3. Použití v ErrorHandler.ts

```typescript
// Před:
const HTTP_MESSAGES: Record<number, string> = {
    400: 'Neplatný požadavek.',
    // 20 řádků hardcoded textů...
};

// Po:
const httpMessage = (code: number): string =>
    Translator.t('errors', `http.${code}`, `HTTP ${code}`);

const codeTitle = (code: number): string =>
    Translator.t('errors', `code_titles.${code}`, 'Error');

const codeUserMessage = (code: number): string =>
    Translator.t('errors', `code_messages.${code}`, 'An error occurred.');

const typeTitle = (type: string): string =>
    Translator.t('errors', `type_titles.${type}`, 'Error');
```

### 6.4. Použití v MessageRenderer.ts

```typescript
// Před:
const DEFAULT_TITLES: Record<MessageLevel, string> = {
    error: 'Chyba',
    success: 'Úspěch',
    warning: 'Varování',
    info: 'Informace',
};

// Po:
const defaultTitle = (level: MessageLevel): string =>
    Translator.t('ui', `notification.${level}`, level);
```

---

## 7. Platformový adaptér — použití vestavěného MysqliAdapter

> Vestavěný `MysqliAdapter` je součástí balíčku — uživatel nemusí psát vlastní adaptér pro MySQL.
> Stačí předat existující `mysqli` instanci a volitelně název tabulky.

### 7.1. DB tabulka pro překlady

```sql
CREATE TABLE pb_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    locale VARCHAR(5) NOT NULL,         -- 'cs', 'en', 'de'
    domain VARCHAR(20) NOT NULL,        -- 'errors', 'ui', 'api', 'config'
    key_path VARCHAR(255) NOT NULL,     -- 'http.400', 'blocks.tone.label'
    value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_translation (locale, domain, key_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 7.2. Použití při inicializaci (nejjednodušší varianta)

```php
// Uživatel má svou existující mysqli instanci:
$mysqli = new \mysqli('localhost', 'user', 'pass', 'my_database');

$bridge = PlatformBridge::create()
    ->withLocale('cs')
    ->withTranslationAdapter(MysqliAdapter::fromMysqli($mysqli))
    ->build();
```

### 7.3. S vlastním názvem tabulky

```php
$adapter = MysqliAdapter::fromMysqli($mysqli, 'my_custom_translations');

$bridge = PlatformBridge::create()
    ->withLocale('cs')
    ->withTranslationAdapter($adapter)
    ->build();
```

### 7.4. Vlastní adaptér (pro jiné DB)

Pro PostgreSQL, API, Redis atd. uživatel implementuje `PlatformAdapterInterface`:

```php
<?php
namespace App\Translation;

use PlatformBridge\Translator\Adapter\PlatformAdapterInterface;
use PlatformBridge\Translator\Domain;

class PostgresAdapter implements PlatformAdapterInterface
{
    public function __construct(private readonly \PgSql\Connection $conn) {}

    public function fetch(string $locale, Domain $domain): array
    {
        $result = pg_query_params(
            $this->conn,
            'SELECT key_path, value FROM translations WHERE locale = $1 AND domain = $2',
            [$locale, $domain->value]
        );

        $translations = [];
        while ($row = pg_fetch_assoc($result)) {
            $translations[$row['key_path']] = $row['value'];
        }
        return $translations;
    }

    public function supports(): bool { return true; }
    public function availableLocales(): array { /* ... */ }
}
```

### 7.5. Bez databáze (standalone mód)

Pokud uživatel nemá DB, Translator automaticky použije `NullAdapter`
a překlady se berou pouze ze statických JSON souborů:

```php
$bridge = PlatformBridge::create()
    ->withLocale('cs')
    // bez withTranslationAdapter() → automaticky NullAdapter
    ->build();
```

---

## 8. Řešení specifického problému: JSON config překlady

### Problém
- `blocks.json` obsahuje texty jako "Friendly", "Goal", "Emoji in subject"
- Bloky jsou **dynamické** — každý uživatel/platforma má jiné, mohou jich být stovky
- Statické `config.json` soubory nedávají smysl pro dynamický obsah

### Řešení: `translatable` flag + DB lookup

1. **`blocks.json` zůstává anglický** (technický/referenční zdroj)
2. **V bloku se označí, co je přeložitelné** přes klíč `translatable`
3. **Překlady jdou výhradně z DB** — doména `config`
4. Pokud překlad v DB neexistuje → fallback na anglický text z JSON

### 8.1. Nová struktura bloku s `translatable`

```json
{
    "tone": {
        "id": "tone",
        "name": "tone",
        "ai_key": "CommunicationTone",
        "component": "select",
        "label": "Tone",
        "translatable": ["label", "options"],
        "rules": {
            "default": "formal",
            "required": true
        },
        "options": [
            { "value": "friendly", "label": "Friendly" },
            { "value": "formal", "label": "Formal" },
            { "value": "informative", "label": "Informative" }
        ]
    },
    "goal": {
        "id": "goal",
        "name": "goal",
        "ai_key": "MessagePurpose",
        "component": "select",
        "label": "Goal",
        "tooltip": "Select the main purpose of the message",
        "translatable": ["label", "tooltip", "options"],
        "options": [
            { "value": "survey", "label": "Survey" },
            { "value": "reminder", "label": "Reminder" }
        ]
    },
    "emoji": {
        "id": "emoji",
        "name": "emoji_enabled",
        "component": "input",
        "variant": "checkbox",
        "label": "Emoji in subject",
        "translatable": ["label", "info"]
    }
}
```

> Bloky bez `translatable` klíče se nepřekládají — text z JSON se použije as-is.

### 8.2. Jak se překlady ukládají v DB

```sql
-- Příklad dat v tabulce pb_translations pro doménu 'config':
INSERT INTO pb_translations (locale, domain, key_path, value) VALUES
('cs', 'config', 'blocks.tone.label', 'Tón komunikace'),
('cs', 'config', 'blocks.tone.options.friendly', 'Přátelský'),
('cs', 'config', 'blocks.tone.options.formal', 'Formální'),
('cs', 'config', 'blocks.tone.options.informative', 'Informativní'),
('cs', 'config', 'blocks.goal.label', 'Cíl'),
('cs', 'config', 'blocks.goal.tooltip', 'Vyberte hlavní účel zprávy'),
('cs', 'config', 'blocks.goal.options.survey', 'Průzkum'),
('cs', 'config', 'blocks.goal.options.reminder', 'Připomínka'),
('cs', 'config', 'blocks.emoji.label', 'Emoji v předmětu'),
('de', 'config', 'blocks.tone.label', 'Kommunikationston'),
('de', 'config', 'blocks.tone.options.friendly', 'Freundlich');
```

### 8.3. Resolve logika — jen pro označená pole

```php
// V FieldFactory nebo FormRenderer — při stavbě fieldu:
private function resolveBlockTranslations(array $block): array
{
    // Pokud blok nemá translatable → vrať beze změny
    $translatable = $block['translatable'] ?? [];
    if (empty($translatable)) {
        return $block;
    }

    $id = $block['id'];

    // Překlad jednoduchých textových polí (label, tooltip, info, small, placeholder)
    $textFields = ['label', 'tooltip', 'info', 'small', 'placeholder'];
    foreach ($textFields as $field) {
        if (in_array($field, $translatable, true) && isset($block[$field])) {
            $block[$field] = $this->translator->t(
                'config',
                "blocks.{$id}.{$field}",
                default: $block[$field]  // fallback = anglický text z JSON
            );
        }
    }

    // Překlad options
    if (in_array('options', $translatable, true) && isset($block['options'])) {
        foreach ($block['options'] as &$option) {
            $option['label'] = $this->translator->t(
                'config',
                "blocks.{$id}.options.{$option['value']}",
                default: $option['label']
            );
        }
    }

    // Překlad group (radio buttons) — stejný vzor jako options
    if (in_array('options', $translatable, true) && isset($block['group'])) {
        foreach ($block['group'] as &$item) {
            $item['label'] = $this->translator->t(
                'config',
                "blocks.{$id}.options.{$item['value']}",
                default: $item['label']
            );
        }
    }

    return $block;
}
```

> **Výhody tohoto přístupu:**
> - Žádné statické `config.json` soubory — vše z DB
> - Uživatel explicitně říká, co chce překládat (`translatable`)
> - Nové bloky automaticky podporují překlady — stačí přidat `translatable` a data do DB
> - Fallback na anglický text z JSON = nikdy se nezobrazí prázdný string
> - Škáluje na stovky bloků — je to jen DB lookup
```

---

## 9. Pořadí implementace

### Fáze 1: Základ (bez DB, bez TS)
1. Vytvořit `Domain` enum
2. Vytvořit `TranslationCatalog`
3. Vytvořit `JsonFileLoader`
4. Vytvořit `Translator` fasádu
5. Vytvořit `resources/lang/cs/` a `en/` JSON soubory (errors, ui, api — BEZ config)
6. Propojit `Translator::fetchTranslations()` s Parserem (už je napojený)
7. Přidat `bootTranslator()` do `PlatformBridge::boot()`

### Fáze 2: DB vrstva + vestavěný MysqliAdapter
1. Vytvořit `DatabaseConnectionInterface`
2. Vytvořit `MysqliConnection`
3. Vytvořit `PlatformAdapterInterface`
4. Vytvořit `MysqliAdapter` (vestavěný)
5. Vytvořit `NullAdapter`
6. Vytvořit `PlatformLoader`
7. Přidat `withTranslationAdapter()` do Builderu
8. Vytvořit SQL migrace pro `pb_translations` tabulku

### Fáze 3: Config překlady z DB
1. Přidat `translatable` klíč do bloků v `blocks.json`
2. Implementovat `resolveBlockTranslations()` v FieldFactory/FormRenderer
3. Config překlady jdou z DB přes doménu `config`
4. Fallback na anglické texty z JSON

### Fáze 4: PHP migrace hardcoded textů
1. Refaktor `AiException` — texty přes Translator
2. Refaktor `ErrorRenderer` — texty přes Translator
3. Refaktor `NestedResult.tpl` — `{_tran}` pro API klíče

### Fáze 5: TS integrace
1. Vytvořit `assets/ts/i18n/Translator.ts`
2. PHP inject `window.__PB_TRANSLATIONS__`
3. Refaktor `ErrorHandler.ts` — texty přes Translator
4. Refaktor `MessageRenderer.ts` — texty přes Translator
5. Refaktor `FormValidator.ts` — defaultMessage přes Translator

### Fáze 6: Cache + optimalizace
1. Volitelný `TranslationCache` (file-based)
2. Lazy loading domén (nenačítat vše najednou)
3. Export pouze potřebných domén pro frontend

---

## 10. Shrnutí klíčových rozhodnutí

| Rozhodnutí | Volba | Důvod |
|---|---|---|
| Formát překladů | JSON (vnořené → flat tečková notace) | Čitelný, funguje v PHP i TS, snadno editovatelný |
| Klíčová konvence | `{doména}.{sekce}.{klíč}` | Jasná struktura, namespace oddělení |
| Fallback strategie | locale → fallbackLocale → default parametr → klíč | Vždy se něco zobrazí |
| Config překlady | **Výhradně z DB** — bloky jsou dynamické per-uživatel | Statické JSON nedávají smysl pro stovky bloků |
| `translatable` flag | V JSON bloku označuje co překládat | Explicitní, žádné zbytečné DB lookupy |
| DB abstrakce | `mysqli` (NE PDO) + `DatabaseConnectionInterface` | Požadavek projektu, tenká abstrakce |
| Vestavěný adaptér | `MysqliAdapter` součástí balíčku | Uživatel nemusí psát adapter pro MySQL |
| Vlastní adaptéry | `PlatformAdapterInterface` pro jiné DB | PostgreSQL, API, Redis atd. |
| TS překlady | PHP injectuje JSON do `<script>` | Žádný extra HTTP request, synchronní dostupnost |
| Zpětná kompatibilita | `fetchTranslations()` statická metoda | Parser.php už volá `Translator::fetchTranslations()` |
| Interpolace | `{:param}` syntax | Jednoduchá, nenáročná na parsing, kompatibilní s PHP i TS |
