# 🤖 PlatformBridge — Dokumentace

> **Verze:** 1.0.1
> **Autor:** Zdeněk Hochman
> **Licence:** Proprietary
> **Namespace:** `PlatformBridge`

Middleware platforma pro integraci interní aplikace se Zoom API. Umožňuje dynamické generování UI formulářů z JSON konfigurace, jejich odesílání na AI API a zobrazování výsledků. Obsahuje vlastní šablonovací engine, HMAC bezpečnost, překladový systém, CLI installer a kompletní TypeScript frontend s transporty (HTTP/SSE/WebSocket).

---

## 📑 Obsah

- [Požadavky](#-požadavky)
- [Instalace](#-instalace)
- [Rychlý start](#-rychlý-start)
- [Architektura](#-architektura)
- [Konfigurace](#-konfigurace)
  - [Builder pattern](#builder-pattern)
  - [JSON konfigurace](#json-konfigurace)
  - [Bridge config](#bridge-config)
  - [Security config](#security-config)
- [Hlavní třídy](#-hlavní-třídy)
  - [PlatformBridge (fasáda)](#platformbridge-fasáda)
  - [ConfigManager](#configmanager)
  - [Template Engine](#template-engine)
  - [Form & FieldFactory](#form--fieldfactory)
  - [HandlerRegistry](#handlerregistry)
  - [AI Client](#ai-client)
  - [SignedParams (HMAC)](#signedparams-hmac)
  - [AssetManager](#assetmanager)
  - [ErrorHandler & ErrorRenderer](#errorhandler--errorrenderer)
  - [Translator](#translator)
  - [VariableResolver](#variableresolver)
  - [PathResolver](#pathresolver)
  - [JsonGuard](#jsonguard)
- [Šablonovací engine](#-šablonovací-engine)
- [Handlery formulářových polí](#-handlery-formulářových-polí)
- [AI API vrstva](#-ai-api-vrstva)
- [Endpointy](#-endpointy)
- [Frontend (TypeScript)](#-frontend-typescript)
- [Build systém](#-build-systém)
- [CLI Installer](#-cli-installer)
- [Doporučení](#-doporučení)
- [Upozornění](#-upozornění)
- [Hacky a triky](#-hacky-a-triky)

---

## 📋 Požadavky

| Požadavek | Verze |
|---|---|
| **PHP** | >= 8.1 |
| **ext-curl** | * |
| **ext-json** | * |
| **Node.js** | >= 18 (pro build frontendu) |
| **Composer** | >= 2.0 |

---

## 📦 Instalace

### 1. Composer (jako balíček)

```bash
composer require platformbridge/platform-bridge
```

Po instalaci se automaticky spustí CLI installer (`bin/platformbridge install`), který publikuje konfigurační soubory a assety.

### 2. Lokální vývoj (klonování repozitáře)

```bash
git clone http://git.virtualzoom.com:3000/VIRTUALZOOM/ZoomPlatformBridge.git
cd ZoomPlatformBridge

# PHP závislosti
composer install

# Frontend závislosti
npm install
```

### 3. Build frontendu

```bash
# Development (watch mode)
npm run dev

# Production build
npm run build
```

### Frontend závislosti (devDependencies)

| Balíček | Verze | Účel |
|---|---|---|
| `esbuild` | ^0.27.3 | Ultra-rychlý JS/CSS bundler |
| `esbuild-sass-plugin` | ^3.6.0 | SCSS kompilace pro esbuild |
| `typescript` | ^5.9.3 | Typový systém pro JavaScript |

### Adresářová struktura po instalaci

```
├── config/                    # Vývojové konfigurace (lokální vývoj)
│   ├── api.php             # Vývojový API endpoint
│   ├── bridge-config.php   # Vývojová API konfigurace
│   └── security-config.php # Vývojový HMAC klíč
├── assets/                   # Zkompilované assety (JS/CSS)
│   ├── css/pb-main.css
│   └── js/pb-main.js
├── resources/
│   ├── defaults/           # JSON konfigurace (blocks, layouts, generators)
│   └── views/              # Šablony (.tpl)
├── var/cache/              # Zkompilované cache šablon
```

---

## 🚀 Rychlý start

### Minimální použití (výchozí konfigurace)

```php
require_once __DIR__ . '/vendor/autoload.php';

use PlatformBridge\PlatformBridge;

$bridge = PlatformBridge::createDefault();
```

> ⚠️ `createDefault()` slouží primárně pro testování. V produkci používejte Builder pattern.

### Produkční použití (Builder pattern)

```php
require_once __DIR__ . '/vendor/autoload.php';

use PlatformBridge\PlatformBridge;

$bridge = PlatformBridge::create()
    ->withConfigPath(__DIR__ . '/resources/defaults')
    ->withViewsPath(__DIR__ . '/resources/views')
    ->withSecretKey(true)   // HMAC podepisování parametrů
    ->withParamsTtl(3600)   // Expirace podepsaných dat (1 hodina)
    ->withLocale('cs')      // Jazyk aplikace
    ->build();
```

### Použití s překladovým systémem (mysqli)

```php
$mysqli = new \mysqli('localhost', 'root', '', 'platform_bridge', 3306);

$bridge = PlatformBridge::create()
    ->withConfigPath(__DIR__ . '/resources/defaults')
    ->withSecretKey(true)
    ->withLocale('cs')
    ->withMysqli($mysqli)   // Překladový systém z databáze
    ->build();
```

### Vykreslení formuláře

```php
$generatorId = $_GET['generator'] ?? 'subject';

$html = $bridge->renderFullForm($generatorId, [
    'get'     => ['web_id' => 1157],
    'body'    => ['client_id' => 633],
    'headers' => ['X-Custom-Header' => 'MyValue'],
    // Dynamické hodnoty propsané do formulářových bloků (match podle 'name' nebo 'id')
    'inject'  => ['template_id' => 42],
]);

echo $html;
```

---

## 🏗 Architektura

```
┌─────────────────────────────────────────────────────┐
│                   PlatformBridge                     │  ← Hlavní fasáda
│                   (Entry Point)                      │
├─────────────┬───────────┬───────────┬───────────────┤
│ ConfigManager│ Template  │  Runtime  │  AI Client    │
│ (JSON cfg)  │ Engine    │ Renderer  │  (cURL)       │
├─────────────┼───────────┼───────────┼───────────────┤
│ ConfigLoader│ Parser    │FormRender │ AiRequest     │
│ ConfigValid.│ VarModif. │LayoutMgr  │ AiResponse    │
│ ConfigResol.│           │FieldFact. │ ApiHandler    │
├─────────────┼───────────┼───────────┼───────────────┤
│ blocks.json │ *.tpl     │ Handlers  │ Endpoints     │
│ layouts.json│ (views)   │ (Strategy)│ (Registry)    │
│ generators  │           │           │               │
├─────────────┴───────────┴───────────┼───────────────┤
│      Security Layer     │ Translator│  Asset Mgr    │
│  SignedParams / JsonGuard│ + VarRes. │  (CSS/JS)     │
├─────────────────────────┼───────────┼───────────────┤
│     Paths (PathResolver)│ Installer │  Error Layer  │
│   PathsConfig / UrlRes. │ (CLI bin) │ Handler+Rend. │
└─────────────────────────┴───────────┴───────────────┘
```

**Návrhové vzory použité v projektu:**
- **Builder** — `PlatformBridgeBuilder` pro konfiguraci instance
- **Facade** — `PlatformBridge` jako jediný vstupní bod
- **Strategy** — `FieldHandler` → různé handlery pro různé typy polí
- **Factory** — `FieldFactory`, `FormElementFactory` pro vytváření elementů
- **Registry** — `HandlerRegistry`, `EndpointRegistry` pro registraci komponent
- **Value Object** — `PlatformBridgeConfig`, `AiResponse`, DTO třídy (`PathsDto`, `SecurityDto`, `UrlsDto`, `TranslationsDto`)
- **Template Method** — `FieldConfigurator` jako abstraktní báze handlerů
- **PHP 8 Attributes** — `#[Endpoint]` atribut pro deklarativní registraci AI endpointů

---

## ⚙ Konfigurace

### Builder pattern

`PlatformBridgeBuilder` poskytuje fluent API pro konfiguraci:

| Metoda | Popis | Výchozí |
|---|---|---|
| `withConfigPath(string)` | Cesta ke složce s JSON konfiguracemi | Automaticky via `PathResolver` |
| `withViewsPath(string)` | Cesta k šablonám (.tpl) | Automaticky via `PathResolver` |
| `withLocale(string)` | Jazyk aplikace | `'cs'` |
| `withSecretKey(bool)` | Zapne/vypne HMAC podepisování | `false` |
| `withParamsTtl(int)` | TTL podepsaných parametrů (sekundy) | `null` (bez expirace) |
| `withMysqli(\mysqli, string)` | Připojení pro překladový systém z DB | `null` (bez DB překladů) |
| `build()` | Sestaví instanci `PlatformBridge` | — |

> **Poznámka:** Cesty se automaticky resolvují přes `PathResolver` podle kontextu (standalone vs. vendor). Explicitní nastavení cest je potřeba pouze pro nestandardní struktury.

### JSON konfigurace

Konfigurace je rozdělena do 3 souborů ve složce `resources/defaults/`:

#### `blocks.json` — Definice formulářových bloků

Každý blok definuje jeden formulářový prvek:

```json
{
    "blocks": {
        "language": {
            "id": "language",
            "name": "lang",
            "ai_key": "Language",
            "component": "input",
            "variant": "radio",
            "label": "{$config.language.label|Text language variant}",
            "rules": {
                "default": "en",
                "required": true
            },
            "group": [
                { "value": "cs", "label": "CZ" },
                { "value": "en", "label": "EN" }
            ]
        },
        "tone": {
            "id": "tone",
            "name": "tone",
            "ai_key": "CommunicationTone",
            "component": "select",
            "label": "{$config.tone.label|Tón}",
            "rules": {
                "default": "formal",
                "required": true
            },
            "options": [
                { "value": "friendly", "label": "Friendly" },
                { "value": "formal", "label": "Formal" }
            ]
        }
    }
}
```

> **Překladové proměnné:** Textové hodnoty v JSON mohou obsahovat `{$doména.klíč|Fallback}` — tyto se při načtení nahradí přes `VariableResolver` aktuálním překladem.

**Klíčové atributy bloku:**

| Klíč | Typ | Popis |
|---|---|---|
| `id` | string | Unikátní identifikátor bloku |
| `name` | string | HTML atribut `name` |
| `ai_key` | string | Klíč odesílaný do AI API |
| `component` | string | Typ komponenty: `input`, `select`, `textarea` |
| `variant` | string | Varianta: `text`, `radio`, `checkbox`, `hidden`, `email`... |
| `label` | string | Popisek pole (podporuje překladové proměnné) |
| `small` | string | Doplňkový text pod polem |
| `tooltip` | string | Tooltip/nápověda |
| `value` | mixed | Výchozí hodnota |
| `rules` | object | Validační pravidla a chování |
| `options` | array | Možnosti pro `select` |
| `group` | array | Skupina pro `radio` buttony |
| `meta` | object | Data-* atributy |

**Podporovaná pravidla (`rules`):**

| Pravidlo | Typ | Popis |
|---|---|---|
| `required` | bool | Povinné pole |
| `default` | mixed | Výchozí hodnota |
| `placeholder` | string | Placeholder text |
| `minlength` | int | Minimální délka |
| `maxlength` | int | Maximální délka |
| `readonly` | bool | Pouze pro čtení |
| `disabled` | bool | Zakázané pole |
| `checked` | bool | Předvybraný checkbox |
| `autocomplete` | string | Autocomplete atribut |
| `visible_if` | object | Podmíněná viditelnost (`{ "pole": "hodnota" }`) |

#### `layouts.json` — Rozložení sekcí a bloků

Definuje CSS Grid layout formuláře:

```json
{
    "layouts": {
        "subject_advanced": {
            "sections": [
                {
                    "id": "source",
                    "column_template": "auto auto 1fr",
                    "blocks": [
                        { "ref": "topic_source" },
                        { "ref": "email_topic", "grid_column": "1 / -1" },
                        { "ref": "template_id" }
                    ]
                },
                {
                    "id": "settings",
                    "columns": 5,
                    "blocks": [
                        { "ref": "tone", "row_span": 2 },
                        { "ref": "goal", "row_span": 2 },
                        { "ref": "emoji" },
                        { "ref": "personalization" }
                    ]
                }
            ]
        }
    }
}
```

**Layout atributy:**

| Klíč | Typ | Popis |
|---|---|---|
| `columns` | int | Počet sloupců gridu (layout/sekce) |
| `column_template` | string | CSS `grid-template-columns` (např. `"auto auto 1fr"`) |
| `span` | int | Sloupcový span bloku |
| `row_span` | int | Řádkový span bloku |
| `grid_column` | string | Explicitní `grid-column` (např. `"1 / -1"`) |
| `grid_row` | string | Explicitní `grid-row` |

#### `generators.json` — Generátory AI obsahu

Propojuje layout s API endpointem:

```json
{
    "generators": {
        "subject": {
            "id": "subject",
            "label": "Email Subjects (Advanced)",
            "layout_ref": "subject_advanced",
            "config": {
                "endpoint": "CreateSubject",
                "api": {
                    "request_amount": "3"
                }
            }
        }
    }
}
```

### Bridge config

Soubor `bridge-config.php` obsahuje konfiguraci AI API připojení:

```php
<?php
if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

return [
    // API klíč pro autentizaci vůči AI provideru
    'api_key'     => 'YOUR-API-KEY',

    // Timeout HTTP požadavku na AI v sekundách
    'timeout'     => 30,

    // Počet opakování při selhání požadavku
    'max_retries' => 3,

    // URL AI API endpointu
    'base_url'    => 'https://api.example.com/v2/AI/',

    // Registrace vlastních AI endpointů
    'endpoints' => [
        ['class' => CreateSubjectEndpoint::class, 'file' => __DIR__ . '/../CreateSubjectEndpoint.php'],
    ],
];
```

### Security config

Soubor `security-config.php` obsahuje HMAC bezpečnostní nastavení (oddělený od bridge-config):

```php
<?php
if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

return [
    // Tajný klíč pro podepisování parametrů (min. 32 znaků)
    'secretKey' => 'put-your-long-super-secret-key-here-32chars-minimum',

    // Platnost podepsaných parametrů v sekundách (null = bez expirace)
    'ttl' => 3600,
];
```

> ⚠️ **Secret key musí mít minimálně 32 znaků.** Pro generování použijte:
> ```php
> echo \PlatformBridge\Security\SignedParams::generateSecretKey();
> ```

> ⚠️ **Oba konfigurační soubory nesmí být veřejně přístupné.** Ujistěte se, že nejsou v public webroot nebo jsou chráněny `.htaccess`.

---

## 🧩 Hlavní třídy

### PlatformBridge (fasáda)

**Namespace:** `PlatformBridge\PlatformBridge`

Vstupní bod celé knihovny. Zapouzdřuje všechny interní komponenty.

```php
// Vytvoření instance
$bridge = PlatformBridge::create()->build();     // Builder
$bridge = PlatformBridge::createDefault();        // Výchozí konfigurace

// Hlavní API
$html = $bridge->renderFullForm('subject', [...]);  // Kompletní formulář + assety
$html = $bridge->getAssets();                       // Pouze CSS/JS tagy

// Přístup k interním komponentám
$engine     = $bridge->getTemplateEngine();         // Template engine
$config     = $bridge->getConfig();                 // Konfigurace (PlatformBridgeConfig)
$translator = $bridge->getTranslator();             // Překladový systém
$resolver   = $bridge->getVariableResolver();       // VariableResolver pro {$domain.key}
```

**Metoda `renderFullForm()`** — hlavní flow:
1. Extrahuje `inject` hodnoty z parametrů
2. Zavolá `FormRenderer::build()` pro sestavení sekcí
3. Sestaví parametry (endpoint, request_amount, locale, ...)
4. Podepíše parametry přes HMAC (pokud aktivní)
5. Renderuje Wrapper šablonu
6. Připojí CSS/JS assety

**Boot sekvence (interní):**
1. `bootErrorHandler()` — globální error handler
2. `bootTranslator()` — překladový systém + VariableResolver
3. `bootConfig()` — načtení JSON konfigurace (s proměnnými)
4. `bootTemplateEngine()` — šablonovací engine
5. `bootHandlers()` — registrace formulářových handlerů
6. `bootFormRenderer()` — inicializace FormRenderer
7. `bootAssetManager()` — správce assetů
8. `bootSecurity()` — HMAC podepisování

---

### ConfigManager

**Namespace:** `PlatformBridge\Config\ConfigManager`

Centrální třída pro práci s JSON konfigurací. Načítá, validuje a poskytuje přístup ke třem konfiguračním souborům.

```php
$config = ConfigManager::create('/path/to/config');

// Generátory
$generator = $config->getGenerator('subject');
$all       = $config->getAllGenerators();
$exists    = $config->hasGenerator('subject');
$label     = $config->getGeneratorLabel('subject');

// Hodnoty z konfigurace generátoru (tečková notace)
$endpoint = $config->getConfigValue('subject', 'endpoint');
$model    = $config->getConfigValue('subject', 'api.request_amount');

// Layouty a bloky
$layout   = $config->getLayout('subject_advanced');
$sections = $config->getResolvedSections('subject_advanced');
$blocks   = $config->getSectionBlocks('subject_advanced', 'settings');
```

**Klíčové vlastnosti:**
- Lazy loading s cache rozřešených generátorů/layoutů
- Cross-validace vztahů (generator → layout → blocks)
- Podpora tečkové notace pro přístup k nested hodnotám

**ConfigLoader — merge strategie:**
Konfigurace používá **full override** strategii — pokud existuje uživatelský konfigurační soubor, použije se POUZE ten (žádné slučování s výchozími hodnotami). Kandidáti se hledají v pořadí: chráněný `.json.php` → nechráněný `.json`.

---

### Template Engine

**Namespace:** `PlatformBridge\Template\Engine`

Vlastní šablonovací engine s Smarty-like syntaxí a kompilací do PHP.

```php
$engine = new Engine([
    'tpl_dir'   => '/path/to/views',
    'cache_dir' => '/path/to/cache',
    'debug'     => false,
]);

// Přiřazení proměnných a renderování
$html = $engine
    ->assign(['title' => 'Hello', 'items' => [1, 2, 3]])
    ->render('/Atoms/Wrapper');

// Vyčištění kontextu
$engine->clear();
```

**Vlastnosti:**
- Kompilace šablon do PHP s cache
- Automatické čištění expirovaných cache souborů
- File locking při kompilaci (LOCK_SH)
- Blacklist nebezpečných PHP funkcí

---

### Form & FieldFactory

**Namespace:** `PlatformBridge\Form\Form`

Statické API pro deklarativní vytváření formulářových polí.

```php
// Dynamické volání přes __callStatic
Form::Input('email_topic', 'email_topic', [...]);
Form::Select('tone', 'tone', [...]);
Form::Textarea('content', 'content', [...]);
Form::TickBox('emoji', 'emoji', [...]);

// Řetězení
Form::setLabel(['text' => 'E-mail topic']);
Form::setSmall(['text' => 'Min 30 znaků']);

// Renderování
$html = Form::render($engine);                        // Bez layout wrapperů
$html = Form::renderWrapped($engine, $blockDefs);     // S CSS Grid wrappery
```

**`FieldFactory`** — továrna delegující na `HandlerRegistry`:

```php
$factory = new FieldFactory($registry);
$elements = $factory->createFromBlock($blockDefinition);
```

**Runtime vrstvy:**
- `FormRenderer` — sestavuje sekce formuláře z JSON konfigurace
- `LayoutManager` — generuje `data-layout-*` atributy pro CSS Grid (span, row-span, grid-column, grid-row) a `data-visible-if` pro podmíněnou viditelnost

---

### HandlerRegistry

**Namespace:** `PlatformBridge\Handler\HandlerRegistry`

Strategy pattern registr pro zpracování různých typů formulářových bloků.

```php
$registry = new HandlerRegistry();

// Registrace handlerů
$registry->addHandler(new TextHandler());
$registry->addHandler(new SelectHandler());
$registry->addHandler(new RadioHandler());

// Nastavení výchozího handleru
$registry->setDefaultHandler(TextHandler::class);

// Mapování vlastní varianty
$registry->mapVariant('color-picker', CustomColorPickerHandler::class);

// Resolving
$handler = $registry->resolve($block);  // Najde vhodný handler
```

**Priorita resolvingu:**
1. Explicitní varianta z `variantMap`
2. První registrovaný handler, který `supports()` blok
3. Výchozí handler (fallback)

---

### AI Client

**Namespace:** `PlatformBridge\AI\Client`

HTTP klient pro komunikaci s AI API přes cURL.

```php
$config = AiClientConfig::fromArray([
    'api_key'    => 'your-api-key',
    'base_url'   => 'https://api.virtualzoom.com/v2/AI',
    'timeout'    => 30,
    'verify_ssl' => true,
]);

$client = new AiClient($config);

// Sestavení a odeslání requestu
$request = AiRequest::to('CreateSubject')
    ->usingMethod('POST')
    ->withPrompt(['Language' => 'cs', 'CommunicationTone' => 'formal'])
    ->withQueryParams(['web_id' => 1157])
    ->withHeader('X-Custom', 'Value');

$response = $client->send($request);

// Práce s odpovědí
if ($response->isSuccess()) {
    $data = $response->getResponse();
    $value = $response->get('results.0.text');  // Tečková notace
} else {
    $error = $response->getError();
}

// Nebo s vyhozením výjimky
$data = $response->getOrFail();
```

**AiException error kódy:**

| Konstanta | Kód | Popis |
|---|---|---|
| `ERROR_INVALID_REQUEST` | 1001 | Neplatný request |
| `ERROR_VALIDATION` | 1002 | Validační chyba |
| `ERROR_CONNECTION` | 1003 | Chyba připojení |
| `ERROR_TIMEOUT` | 1004 | Timeout |
| `ERROR_INVALID_RESPONSE` | 1005 | Neplatná odpověď |
| `ERROR_API` | 1006 | API chyba |

---

### SignedParams (HMAC)

**Namespace:** `PlatformBridge\Security\SignedParams`

HMAC-SHA256 podepisování parametrů pro bezpečný přenos dat.

```php
$signer = new SignedParams(
    secretKey: 'min-32-chars-long-secret-key-here!!',
    ttl: 3600  // Volitelná expirace (sekundy)
);

// Podepsání
$signed = $signer->sign([
    'get'  => ['web_id' => 1157],
    'body' => ['client_id' => 633],
]);

// Ověření
try {
    $params = $signer->verify($signed);
} catch (SecurityException $e) {
    // Neplatný podpis nebo expirované
}

// Bezpečné ověření (bez výjimky)
if ($signer->isValid($signed)) {
    // OK
}

// Generování secret key
$key = SignedParams::generateSecretKey(32);  // 64-char hex string
```

**Formát výstupu:** `base64url(json({ p: payload_json, s: hmac_signature }))`

---

### AssetManager

**Namespace:** `PlatformBridge\Asset\AssetManager`

Správce CSS/JS assetů s logikou "vložit pouze jednou" (once pattern).

```php
// Automaticky se používá v renderFullForm()
$assets = $bridge->getAssets();  // <link> + <script> tagy

// Manuální použití
$manager = new AssetManager('https://example.com/dist');
$css = $manager->getStyles();    // <link rel="stylesheet">
$js  = $manager->getScripts();   // <script src="...">
$all = $manager->getAssets();    // Oba najednou

// Druhé volání vrátí prázdný string (already rendered)
$css2 = $manager->getStyles();   // ''

// Force reload
$css3 = $manager->getStyles(force: true);  // <link> znovu

// Reset stavu (pro testy)
AssetManager::reset();
```

---

### ErrorHandler & ErrorRenderer

**Namespace:** `PlatformBridge\Error`

Globální error handler s vizuálním výstupem pro vývoj (Whoops-style).

```php
// ErrorRenderer ovládá zobrazení detailů
$renderer = new ErrorRenderer(showDetails: true);   // Dev mód (stack trace)
$renderer = new ErrorRenderer(showDetails: false);  // Produkce (generická zpráva)

// ErrorHandler registruje globální handlery
$handler = new ErrorHandler($renderer);
$handler->register();
```

**Zachytává:**
- Nezachycené výjimky (`set_exception_handler`)
- PHP chyby (`set_error_handler`) — konvertuje na `ErrorException`
- Fatální chyby (`register_shutdown_function`)

**`RenderableException`** — Interface pro výjimky s vlastním HTML renderováním.

> ⚠️ V produkci nastavte `showDetails: false` na `ErrorRenderer` — skryje stack trace a zobrazí generickou chybovou zprávu.

---

### Translator

**Namespace:** `PlatformBridge\Translator\Translator`

Překladový systém s podporou domén, tečkové notace a databázových zdrojů.

```php
// Vytvoření (interně v PlatformBridge::boot())
$translator = Translator::create(
    locale: 'cs',
    langPath: '/path/to/resources/lang',
    platformLoader: $platformLoader,  // Volitelně: překlady z DB
);

// Překlad
$text = $translator->t('ui', 'btn.save', [], 'Save');  // Doména, klíč, parametry, fallback

// Interpolace parametrů
$text = $translator->t('errors', 'field.required', ['field' => 'Email']);
// → "Pole Email je povinné"

// Přístup ke komponentám
$resolver = $translator->getVariableResolver();
$catalog  = $translator->getCatalog();
$locale   = $translator->getLocale();
```

**Zdroje překladů:**
- `JsonFileLoader` — načítá ze souborů `resources/lang/{locale}/{domain}.json`
- `PlatformLoader` → `MysqliAdapter` — načítá z databázové tabulky

**Domény překladů:**
- `ui` — popisky UI prvků
- `errors` — chybové hlášky
- `api` — překlady API odpovědí
- `config` — popisky v JSON konfiguraci

---

### VariableResolver

**Namespace:** `PlatformBridge\Translator\VariableResolver`

Nahrazuje překladové proměnné `{$doména.klíč}` v textech a JSON konfiguracích.

```php
// Nahrazení v řetězci
$resolved = $resolver->resolve('{$config.tone.label|Tón}');
// → "Tón komunikace" (nebo fallback "Tón" pokud překlad neexistuje)

// Rekurzivní nahrazení v poli
$resolvedArray = $resolver->resolveArray($configData);

// Detekce proměnných
if (VariableResolver::hasVariable($text)) {
    // Text obsahuje {$...} proměnné
}
```

**Formát proměnné:** `{$doména.klíč|Volitelný fallback}`

---

### PathResolver

**Namespace:** `PlatformBridge\Paths\PathResolver`

Centralizovaná správa cest s automatickou detekcí kontextu (standalone vs. vendor).

```php
// Automatická detekce kontextu
$paths = PathResolverFactory::auto($rootDir);

// Cesty projektu
$paths->cachePath();          // var/cache/
$paths->assetsPath();         // dist/
$paths->apiFile();            // Cesta k api.php
$paths->securityConfigFile(); // security-config.php
$paths->bridgeConfigFile();   // bridge-config.php

// Cesty balíčku
$paths->viewsPath();          // resources/views/
$paths->configPath();         // resources/defaults/
$paths->langPath();           // resources/lang/
$paths->packageStubsPath();   // resources/stubs/

// Introspekce
$paths->isVendor();            // true pokud instalován přes Composer
$paths->projectRoot();         // Kořen projektu
$paths->packageRoot();         // Kořen balíčku
```

---

### JsonGuard

**Namespace:** `PlatformBridge\Security\JsonGuard`

Ochrana JSON konfiguračních souborů přes PHP exit guard. Zabrání zobrazení obsahu při přímém přístupu z prohlížeče.

```php
// Ochrana JSON obsahu
$protected = JsonGuard::protect($jsonString);   // Přidá PHP exit guard
$original  = JsonGuard::strip($protectedContent); // Odstraní guard

// Práce se soubory
$data = JsonGuard::readFile('/path/to/file.json.php');
JsonGuard::writeProtected('/path/to/output.json.php', $jsonString);

// Konverze existujícího souboru
JsonGuard::convertToProtected('config.json', 'config.json.php', deleteSource: true);

// Zkontrolovat název chráněného souboru
$filename = JsonGuard::protectedFilename('blocks.json'); // → blocks.json.php
```

---

## 📝 Šablonovací engine

Vlastní šablonovací systém s Smarty-like syntaxí, kompilací do PHP a cache.

### Syntaxe šablon

| Syntaxe | Popis | Příklad |
|---|---|---|
| `{$variable}` | Výpis proměnné (escaped) | `{$title}` |
| `{$var:raw}` | Výpis bez HTML escapování | `{$html:raw}` |
| `{$var.key}` | Přístup k nested klíči | `{$user.name}` |
| `{if $cond}...{/if}` | Podmínka | `{if $items}...{/if}` |
| `{if $var == "val"}` | Porovnání | `{if $type == "radio"}` |
| `{elseif $cond}` | Else-if větev | |
| `{else}` | Else větev | |
| `{for $arr as $item}...{/for}` | Cyklus | `{for $data as $section}` |
| `{for $arr as $val on $key}` | Cyklus s klíčem | |
| `{include path, key => $val}` | Include šablony | `{include Components/Icons}` |
| `{_require /path}` | Require šablony | `{_require /Components/Icons}` |
| `{function="fn()"}` | Volání PHP funkce | `{function="strtoupper($name)"}` |
| `{% CONSTANT %}` | PHP konstanta | `{% PHP_EOL %}` |
| `{_tran k='key' d='default'}` | Překlad (vyžaduje Translator) | `{_tran k='btn.save' d='Save'}` |
| `{* komentář *}` | Komentář (odstraní se) | |

### Bezpečnost šablon

Engine obsahuje **blacklist nebezpečných PHP funkcí** (`exec`, `shell_exec`, `eval`, `file_get_contents`, `unlink`, ...). Pokus o jejich volání přes `{function="..."}` bude zablokován.

---

## 🎛 Handlery formulářových polí

Systém používá **Strategy pattern** — každý typ pole má vlastní handler dědící z `FieldConfigurator`.

### Registrované handlery

| Handler | Component | Variant | Popis |
|---|---|---|---|
| `TextHandler` | `input` | `text, email, password, search, url, tel` | Textová pole |
| `NumberHandler` | `input` | `number` | Číselné pole |
| `DateHandler` | `input` | `date` | Datumové pole |
| `HiddenHandler` | `input` | `hidden` | Skryté pole |
| `RadioHandler` | `input` | `radio` | Skupina radio buttonů |
| `CheckboxHandler` | `input` | `checkbox` | Zaškrtávací pole |
| `TickBoxHandler` | `input` | `tickbox` | Toggle/přepínač |
| `FileHandler` | `input` | `file` | Nahrávání souborů |
| `SelectHandler` | `select` | — | Rozbalovací seznam |
| `TextareaHandler` | `textarea` | — | Víceřádkový text |

### Vytvoření vlastního handleru

```php
use PlatformBridge\Handler\Fields\FieldConfigurator;

class ColorPickerHandler extends FieldConfigurator
{
    public function supports(array $block): bool
    {
        return ($block['variant'] ?? '') === 'color-picker';
    }

    public function create(array $block): array
    {
        $this->applyDefaults($block);

        Form::Input($block['name'], $block['id'], [
            'type:type' => 'color',
            'type:value' => $this->defaultValue(),
        ]);

        Form::setLabel(['text' => $block['label'] ?? '']);

        return [$block];
    }
}

// Registrace
$registry->mapVariant('color-picker', ColorPickerHandler::class);
```

---

## 🤖 AI API vrstva

### Architektura

```
AI/
├── Client/                         # HTTP klient
│   ├── AiClient.php                # cURL HTTP klient
│   ├── AiClientConfig.php          # Konfigurace klienta
│   ├── AiRequest.php               # Request builder
│   ├── AiResponse.php              # Response wrapper
│   └── AiResponseRenderer.php      # Renderování odpovědí
├── API/
│   ├── Core/                       # Jádro API zpracování
│   │   ├── ApiHandler.php          # Vstupní bod (bootstrap + handle)
│   │   ├── AiRequestProcessor.php  # Zpracování AI požadavků
│   │   ├── ApiErrorHandler.php     # Chybové odpovědi
│   │   ├── Endpoint/               # Registr a resolving endpointů
│   │   │   ├── EndpointDefinition.php
│   │   │   ├── EndpointFactory.php
│   │   │   ├── EndpointRegistry.php
│   │   │   ├── EndpointResolver.php
│   │   │   ├── FieldMapper.php
│   │   │   └── RegistrationParser.php
│   │   └── Response/               # Parsování a sestavení odpovědí
│   │       ├── ApiResponseBuilder.php
│   │       └── ResponseParser.php
│   ├── Enum/
│   │   ├── HttpMethod.php          # HTTP metody (enum)
│   │   └── ResponseType.php        # Typy odpovědí (enum)
│   └── Types/
│       ├── Attributes/             # PHP 8 atributy pro endpointy
│       │   ├── Endpoint.php        # #[Endpoint] atribut
│       │   └── AttributeEndpoint.php # Bázová třída
│       └── Configurable/
│           └── ConfigurableEndpoint.php # Deklarativní endpointy
└── Exception/
    ├── AiException.php             # Typované výjimky
    └── JsonException.php
```

### Flow API požadavku

1. Frontend odešle formulářová data (JSON POST)
2. `ApiHandler::bootstrap()` inicializuje prostředí:
   - Autodetekuje cesty přes `PathResolverFactory::auto()`
   - Načte `bridge-config.php` a `security-config.php`
   - Zaregistruje uživatelské endpointy do `EndpointRegistry`
3. `ApiHandler::handle()` zpracuje request:
   - Ověří HMAC podpis (`SignedParams::verify()`)
   - `EndpointResolver` najde endpoint v `EndpointRegistry`
   - `AiRequestProcessor` sestaví `AiRequest` z formulářových dat
   - `AiClient` odešle cURL request na AI API
   - `ResponseParser` parsuje odpověď
   - `ApiResponseBuilder` renderuje HTML přes šablony
4. Vrátí JSON odpověď na frontend

### Typy odpovědí endpointů

| Typ | Popis | Příklad |
|---|---|---|
| `string` | Jednoduchý textový výsledek | Překlad textu |
| `array` | Pole klíč-hodnota | Email subject varianty |
| `nested` | Vnořená struktura | Komplexní výsledky |

---

## 🔌 Endpointy

Endpointy definují, jak se zpracovávají jednotlivé AI požadavky. Existují **dva přístupy**:

### 1. PHP 8 Attribute endpoint (doporučený)

Hlavní přístup — metadata se deklarují přes PHP 8 atribut `#[Endpoint]`:

```php
<?php

use PlatformBridge\AI\API\Types\Attributes\{AttributeEndpoint, Endpoint};
use PlatformBridge\AI\API\Enum\ResponseType;

#[Endpoint(
    name: 'CreateSubject',
    generator: 'subject',
    responseType: ResponseType::Nested,
    template: '/Components/NestedResult',
    variantKey: 'type'
)]
class CreateSubjectEndpoint extends AttributeEndpoint
{
    protected function transformInput(array $input, mixed ...$context): array
    {
        [$variant] = $context;

        return match ($variant) {
            'template' => $this->transformTemplateVariant($input),
            'custom'   => $this->transformCustomVariant($input),
            default    => $input,
        };
    }

    private function transformTemplateVariant(array $input): array
    {
        return array_diff_key($input, array_flip(['email_topic', 'topic_source']));
    }

    private function transformCustomVariant(array $input): array
    {
        return array_diff_key($input, array_flip(['template_id', 'topic_source']));
    }
}
```

**Registrace v `bridge-config.php`:**

```php
'endpoints' => [
    ['class' => CreateSubjectEndpoint::class, 'file' => __DIR__ . '/../CreateSubjectEndpoint.php'],
],
```

### 2. Deklarativní (ConfigurableEndpoint)

Alternativní přístup — endpoint se definuje čistě jako pole v `bridge-config.php` bez vlastní PHP třídy:

```php
'endpoints' => [
    'CreateSubject' => [
        'generator_id'  => 'subject',
        'response_type' => 'nested',
        'template'      => '/Components/NestedResult',
        'variant_key'   => 'topic_source',
        'variants'      => [
            'template' => [
                'remove_fields' => ['email_topic', 'topic_source'],
            ],
            'custom' => [
                'remove_fields' => ['template_id', 'topic_source'],
            ],
        ],
    ],
],
```

#### Podporovaná pravidla pro `variants`

| Pravidlo | Typ | Popis |
|---|---|---|
| `remove_fields` | `string[]` | Odstraní zadaná pole ze vstupu |
| `keep_fields` | `string[]` | Ponechá pouze zadaná pole (whitelist) |
| `defaults` | `array` | Doplní výchozí hodnoty pro chybějící klíče |

#### Vlastní transformační funkce

Pro složitější logiku lze místo `variants` použít callable:

```php
'CreateSubject' => [
    'generator_id'  => 'subject',
    'response_type' => 'nested',
    'template'      => '/Components/NestedResult',
    'transform'     => function(array $input, ?string $variant): array {
        return match ($variant) {
            'template' => array_diff_key($input, array_flip(['topic_source'])),
            default    => $input,
        };
    },
],
```

> **Poznámka:** Deklarativní `variants` a callable `transform` se vzájemně vylučují — `transform` má přednost.

---

## 💻 Frontend (TypeScript)

### Architektura

```
assets/ts/
├── pb-main.ts           # Entry point (bundled → dist/js/pb-main.js)
├── app.ts               # Hlavní Application třída
├── Const/               # Konstanty (Components, selektory)
├── Core/                # Jádro aplikace
│   ├── Dom.ts           # DOM utility
│   ├── ErrorHandler.ts  # Klientský error handler
│   ├── EventBus.ts      # Event systém (pub/sub)
│   └── MessageRenderer.ts # Renderování zpráv/notifikací
├── Features/            # Funkční moduly
│   ├── FormValidator.ts        # Validace formulářových dat
│   ├── ResultActionHandler.ts  # Akce nad výsledky (kopie, smazání, regenerace)
│   └── VisibilityController.ts # Podmíněná viditelnost polí (visible_if)
├── Middleware/           # API middleware pipeline
│   ├── CacheMiddleware.ts  # Cache API odpovědí
│   ├── RetryMiddleware.ts  # Opakování při selhání
│   └── TimingMiddleware.ts # Měření doby požadavků
├── Services/             # Služby
│   ├── ApiClient.ts         # Hlavní API klient
│   ├── ApiErrorHandler.ts   # Zpracování API chyb
│   ├── SessionManager.ts    # Správa session
│   ├── TransportRegistry.ts # Registr transportů
│   └── Transports/          # Transportní vrstvy
│       ├── HttpTransport.ts    # Standardní HTTP/fetch
│       ├── SseTransport.ts     # Server-Sent Events streaming
│       └── WebSocketTransport.ts # WebSocket transport
├── Types/                # TypeScript definice
│   ├── Api.ts            # API typy (Request, Response, SSE events)
│   └── Validation.ts     # Validační typy
├── UI/                   # UI komponenty
│   ├── CustomSelect.ts      # Vlastní select komponenta
│   └── LayoutController.ts  # CSS Grid layout controller
└── Utils/
    └── Assert.ts         # Runtime assertions
```

### Flow formuláře

1. **Klik na Generate** → `FormValidator` validuje vstupy
2. **Extrakce dat** → Sbírá data z formuláře
3. **Middleware pipeline** → `RetryMiddleware`, `CacheMiddleware`, `TimingMiddleware`
4. **Transport** → `HttpTransport` odešle POST (nebo `SseTransport` pro streaming)
5. **Zobrazení výsledku** → Vloží HTML do stránky
6. **Akce** → `ResultActionHandler` zajišťuje kopírování, smazání, regeneraci
7. **Viditelnost** → `VisibilityController` spravuje podmíněné zobrazení polí

### Transport Registry

Frontend používá **Transport Registry** pattern pro výběr transportu:

| Transport | Popis | Stav |
|---|---|---|
| `HttpTransport` | Standardní HTTP POST/fetch | ✅ Aktivní |
| `SseTransport` | Server-Sent Events streaming | 🔧 Frontend připraven |
| `WebSocketTransport` | WebSocket real-time | 🔧 Frontend připraven |

> **Poznámka:** SSE a WebSocket transporty jsou na frontendu implementovány, ale backend counterpart (SSE stream) zatím neexistuje.

---

## 🔧 Build systém

Projekt používá **esbuild** pro ultra-rychlý build TypeScript i SCSS.

### Konfigurace (`build.mjs`)

```javascript
// TypeScript → dist/js/pb-main.js
// SCSS → dist/css/pb-main.css
// Dev: sourcemaps zapnuty
// Prod: minified, bez sourcemaps
```

### Příkazy

```bash
npm run dev    # Watch mode (automatický rebuild při změnách)
npm run build  # Jednorázový production build
```

### Výstup

```
dist/
├── css/
│   ├── pb-main.css       # Zkompilované styly
│   └── pb-main.css.map   # Sourcemap (pouze dev)
└── js/
    ├── pb-main.js        # Zkompilovaný JavaScript
    └── pb-main.js.map    # Sourcemap (pouze dev)
```

---

## 🛠 CLI Installer

CLI nástroj pro automatizovanou instalaci a aktualizaci PlatformBridge v cílovém projektu.

### Použití

```bash
# Kompletní instalace (spustí se automaticky po `composer install`)
php bin/platformbridge install

# Aktualizace assetů a API endpointu
php bin/platformbridge update

# Inicializace konfigurace
php bin/platformbridge init

# S přepsáním existujících souborů
php bin/platformbridge install --force

# Pouze vybrané kroky
php bin/platformbridge install --only=assets,config
```

### Kroky instalace

| Krok | Popis |
|---|---|
| `init` | Inicializace konfiguračního souboru |
| `dirs` | Vytvoření potřebných adresářů |
| `guard` | Ochrana JSON souborů (JsonGuard) |
| `assets` | Publikování JS/CSS assetů |
| `api` | Publikování API endpointu |
| `config` | Publikování bridge-config.php |
| `security` | Publikování security-config.php |
| `translations` | Publikování překladů |
| `cache` | Příprava cache adresáře |

### Provisioner třídy

- `ConfigProvisioner` — publikování konfiguračních souborů
- `DirectoryProvisioner` — vytváření adresářové struktury
- `AssetProvisioner` — kopírování zkompilovaných assetů
- `FileProvisioner` — obecné kopírování souborů
- `StubPublisher` — bezpečné publikování stubů (přeskočí existující)

---

## 💡 Doporučení

### 1. Vždy používejte Builder pattern
```php
// ✅ Správně
$bridge = PlatformBridge::create()
    ->withConfigPath(...)
    ->withSecretKey(true)
    ->build();

// ❌ Nepoužívejte v produkci
$bridge = PlatformBridge::createDefault();
```

### 2. Zapněte HMAC podepisování v produkci
HMAC zajišťuje, že parametry (endpoint URL, API klíče) nemůže uživatel modifikovat na frontendu.

### 3. Nastavte TTL podepsaných parametrů
```php
->withParamsTtl(3600)  // 1 hodina je rozumný kompromis
```

### 4. Cache adresář musí být zapisovatelný
Template engine kompiluje šablony do PHP souborů. Cache adresář (`var/cache/`) musí mít oprávnění **0755**.

### 5. Vytvořte vlastní handlery pro speciální pole
Místo hackování existujících handlerů vždy vytvořte nový a zaregistrujte přes `mapVariant()`.

### 6. Používejte `inject` pro dynamické hodnoty
```php
$bridge->renderFullForm('subject', [
    'inject' => ['template_id' => 42],  // Propíše se do bloku s name/id "template_id"
]);
```

### 7. JSON konfigurace — cross-validace
Systém automaticky validuje vztahy mezi `generators → layouts → blocks`. Pokud blok referencovaný v layoutu neexistuje v `blocks.json`, dostanete jasnou chybovou hlášku.

### 8. Preferujte PHP 8 Attribute endpointy
```php
// ✅ Doporučeno — typově bezpečné, IDE-friendly
#[Endpoint(name: 'MyEndpoint', generator: 'my-gen', responseType: ResponseType::Nested)]
class MyEndpoint extends AttributeEndpoint { ... }

// ⚠️ Alternativa — bez vlastní třídy, ale méně flexibilní
'MyEndpoint' => ['generator_id' => 'my-gen', 'response_type' => 'nested', ...]
```

### 9. Využívejte překladové proměnné v JSON
```json
{
    "label": "{$config.tone.label|Tón}"
}
```
`VariableResolver` automaticky nahradí `{$doména.klíč|Fallback}` při načtení konfigurace.

---

## ⚠️ Upozornění

### 1. Secret key minimální délka
Secret key pro HMAC **musí mít minimálně 32 znaků**. Kratší klíč vyhodí `InvalidArgumentException`.

### 2. Konfigurační soubory nesmí být veřejně přístupné
`bridge-config.php` a `security-config.php` obsahují citlivé údaje. Ujistěte se, že nejsou v public webroot nebo jsou chráněny `.htaccess`. JSON konfigurace je možné chránit přes `JsonGuard`.

### 3. Template engine cache — rekompilace
**V aktuální verzi se šablony rekompilují při KAŽDÉM požadavku** (optimalizace pro vývoj). TODO v kódu naznačuje, že kontrola `filemtime()` bude odkomentována pro produkci.

### 4. Translator — částečně aktivní
Překladový systém (`Translator`) je aktivní — `VariableResolver` nahrazuje proměnné v JSON konfiguracích. Integrace do template enginu (`{_tran}` tag) je připravena, ale zatím zakomentovaná. Metody `createTranslationEndpoint()` a `translateResponseKeys()` jsou zakomentované.

### 5. SSE streaming — stav implementace
Frontend SSE transport (`SseTransport.ts`) je plně implementován. Backend SSE stream (server-side) zatím neexistuje — API používá standardní JSON odpovědi.

### 6. Statický stav třídy `Form`
`Form` používá statické properties (`$elements`, `$currentBlockId`). **Nevolat paralelně** — vždy sestavit jeden formulář naráz.

### 7. `AssetManager` — globální stav
`AssetManager` má statické `$rendered` flagy. Pokud renderujete více formulářů na jedné stránce, CSS/JS se vloží pouze jednou (to je záměr, ne bug).

### 8. ErrorHandler přepisuje globální handlery
`ErrorHandler::register()` nastaví `set_exception_handler`, `set_error_handler` a `register_shutdown_function`. V rámci většího frameworku to může kolidovat s existujícími handlery.

---

## 🔮 Hacky a triky

### 1. Vynucení regenerace jednoho klíče (Single-Key Request)

API podporuje regeneraci jednoho konkrétního výsledku bez regenerace celé sady. Frontend posílá `single_key` parametr:

```javascript
// Frontend automaticky přidá single_key při kliknutí na "regenerate" u konkrétního výsledku
{ single_key: "Subject_2" }
```
To ušetří API tokeny — místo 3 variants se regeneruje jen 1.

### 2. Podmíněná viditelnost polí

Pole se automaticky skrývají/zobrazují na základě hodnoty jiného pole:

```json
{
    "rules": {
        "visible_if": {
            "topic_source": "custom"
        }
    }
}
```
Frontend `VisibilityController` to zpracuje automaticky — žádný vlastní kód není potřeba.

### 3. Přímý přístup k Template Engine

Pro renderování vlastních šablon mimo formulář:

```php
$engine = $bridge->getTemplateEngine();
$html = $engine
    ->assign(['custom' => 'data'])
    ->render('/path/to/custom/template');
```

### 4. Vlastní data-* atributy přes `meta`

Každý blok může mít libovolné `data-*` atributy:

```json
{
    "meta": {
        "tracking-id": "abc123",
        "analytics": "true"
    }
}
```
Vyrenderuje se jako: `data-tracking-id="abc123" data-analytics="true"`

### 5. Generování secret key z CLI

```bash
php -r "require 'vendor/autoload.php'; echo \PlatformBridge\Security\SignedParams::generateSecretKey();"
```

### 6. Vymazání cache šablon

Cache se automaticky čistí po 3000 sekundách (~50 minut). Pro okamžité vymazání:

```bash
rm -rf var/cache/*.cache.php
```

Nebo programově v PHP:

```php
array_map('unlink', glob('var/cache/*.cache.php'));
```

### 7. Override výchozích cest bez úpravy kódu

Builder automaticky resolvuje cesty přes `PathResolver`. Pro override stačí zavolat příslušnou `with*()` metodu:

```php
$bridge = PlatformBridge::create()
    ->withViewsPath('/my/custom/views')   // Override šablon
    ->withConfigPath('/my/custom/config') // Override konfigurace
    ->build();
```
Tím můžete mít **jiné formuláře pro jiné sekce** aplikace se sdíleným jádrem.

### 8. Raw HTML výstup v šablonách

Pro vložení HTML bez escapování (pozor na XSS!):

```
{$htmlContent:raw}
```

### 9. Injekce hodnot do skrytých polí

Skvělé pro předání kontextových dat (template ID, session ID) do AI requestu:

```php
$html = $bridge->renderFullForm('subject', [
    'inject' => [
        'template_id' => $currentTemplateId,  // Propíše se do hidden fieldu
    ],
]);
```

### 10. Dev adresář pro lokální vývoj

Adresář `dev/` obsahuje vývojové verze konfigurace (mimo Git):
- `dev/api.php` — lokální API endpoint
- `dev/bridge-config.php` — vývojová API konfigurace
- `dev/security-config.php` — vývojový HMAC klíč

Tyto soubory odpovídají produkčním stubům v `resources/stubs/`, ale s předvyplněnými hodnotami pro lokální vývoj.

---

## 📁 Přehled souborů projektu

| Soubor / Složka | Popis |
|---|---|
| `index.php` | Entry point — vrací FQCN třídy pro autoload |
| `demo.php` | Demo skript pro lokální testování |
| `composer.json` | PHP závislosti a autoload konfigurace |
| `package.json` | Node.js závislosti pro frontend build |
| `build.mjs` | Konfigurace esbuild (TS + SCSS) |
| `tsconfig.json` | TypeScript konfigurace |
| `bin/platformbridge` | CLI installer (Composer bin) |
| `src/PlatformBridge/` | Kompletní PHP zdrojový kód knihovny |
| `resources/defaults/` | JSON konfigurace (blocks, layouts, generators) |
| `resources/lang/` | Překlady (cs/, en/) |
| `resources/stubs/` | Šablony pro publikování installerem |
| `resources/views/` | Šablony (.tpl) |
| `assets/ts/` | TypeScript zdrojové soubory |
| `assets/scss/` | SCSS zdrojové soubory |
| `dist/` | Zkompilované CSS/JS (build výstup) |
| `dev/` | Vývojové konfigurace (lokální vývoj) |
| `var/cache/` | Cache zkompilovaných šablon |
| `docs/` | Dokumentace |

### Kompletní struktura `src/PlatformBridge/`

```
src/PlatformBridge/
├── PlatformBridge.php           # Hlavní fasáda
├── PlatformBridgeBuilder.php    # Builder pro konfiguraci
├── PlatformBridgeConfig.php     # Immutable konfigurace (Value Object)
├── AI/                          # AI API vrstva
│   ├── Client/                  # HTTP klient (AiClient, AiRequest, AiResponse, ...)
│   ├── API/
│   │   ├── Core/                # ApiHandler, AiRequestProcessor, Endpoint/, Response/
│   │   ├── Enum/                # HttpMethod, ResponseType
│   │   └── Types/               # Attributes/ (PHP 8 endpointy), Configurable/
│   └── Exception/               # AiException, JsonException
├── Asset/                       # AssetManager
├── Config/                      # ConfigManager, ConfigLoader, ConfigValidator, ConfigResolver
│   └── DTO/                     # PathsDto, SecurityDto, UrlsDto, TranslationsDto
├── Error/                       # ErrorHandler, ErrorRenderer, RenderableException
├── Form/                        # Form (statické API), Element/ (Input, Select, ...), Factory/
├── Handler/                     # FieldFactory, HandlerRegistry, Fields/ (handlery)
├── Installer/                   # Installer, Provisioners/, Publisher/
├── Paths/                       # PathResolver, PathResolverFactory, PathsConfig, UrlResolver
├── Runtime/                     # FormRenderer, LayoutManager
├── Security/                    # SignedParams, JsonGuard, Exception/
├── Shared/                      # Exception/ (ConfigException, FileException, JsonException)
│   └── Utils/                   # FileUtils, JsonUtils, TypeUtils
├── Template/                    # Engine, Parser, VariableModifier
└── Translator/                  # Translator, VariableResolver, TranslationCatalog, Domain
    ├── Adapter/                 # MysqliAdapter
    ├── Database/                # MysqliConnection, TableProvisioner
    └── Loader/                  # JsonFileLoader, PlatformLoader
```

---

> **Verze dokumentace:** 1.0.1 | **Poslední aktualizace:** Duben 2026
