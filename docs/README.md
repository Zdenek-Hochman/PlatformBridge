# 🤖 PlatformBridge — Dokumentace

> **Verze:** 1.0.0
> **Autor:** Zdeněk Hochman
> **Licence:** Proprietary
> **Namespace:** `Zoom\PlatformBridge`

Middleware platforma pro integraci interní aplikace se Zoom API. Umožňuje dynamické generování UI formulářů z JSON konfigurace, jejich odesílání na AI API a zobrazování výsledků. Obsahuje vlastní šablonovací engine, HMAC bezpečnost, SSE streaming a kompletní frontend.

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
- [Hlavní třídy](#-hlavní-třídy)
  - [PlatformBridge (fasáda)](#platformbridge-fasáda)
  - [ConfigManager](#configmanager)
  - [Template Engine](#template-engine)
  - [Form & FieldFactory](#form--fieldfactory)
  - [HandlerRegistry](#handlerregistry)
  - [AI Client](#ai-client)
  - [SignedParams (HMAC)](#signedparams-hmac)
  - [AssetManager](#assetmanager)
  - [ErrorHandler](#errorhandler)
- [Šablonovací engine](#-šablonovací-engine)
- [Handlery formulářových polí](#-handlery-formulářových-polí)
- [AI API vrstva](#-ai-api-vrstva)
- [SSE Streaming](#-sse-streaming)
- [Frontend (TypeScript)](#-frontend-typescript)
- [Build systém](#-build-systém)
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
composer require zoom/platform-bridge
```

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
├── assets/
│   ├── dist/           # Zkompilované assety (JS/CSS)
│   └── src/
│       ├── scss/       # Zdrojové SCSS styly
│       └── ts/         # Zdrojový TypeScript
├── resources/
│   ├── config/
│   │   ├── bridge-config.php   # Secret key & TTL
│   │   └── defaults/           # JSON konfigurace (blocks, layouts, generators)
│   └── views/                  # Šablony (.tpl)
├── src/PlatformBridge/         # PHP zdrojový kód
├── var/cache/                  # Zkompilované cache šablon
└── vendor/                     # Composer závislosti
```

---

## 🚀 Rychlý start

### Minimální použití (výchozí konfigurace)

```php
require_once __DIR__ . '/vendor/autoload.php';

use Zoom\PlatformBridge\PlatformBridge;

$bridge = PlatformBridge::createDefault();
```

> ⚠️ `createDefault()` slouží primárně pro testování. V produkci používejte Builder pattern.

### Produkční použití (Builder pattern)

```php
require_once __DIR__ . '/vendor/autoload.php';

use Zoom\PlatformBridge\PlatformBridge;

$bridge = PlatformBridge::create()
    ->withConfigPath(__DIR__ . '/resources/config/defaults')
    ->withViewsPath(__DIR__ . '/resources/views')
    ->withCachePath(__DIR__ . '/var/cache')
    ->withSecretKey(true)   // HMAC podepisování parametrů
    ->withParamsTtl(3600)   // Expirace podepsaných dat (1 hodina)
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
│ ConfigManager│ Template  │  Form     │  AI Client    │
│ (JSON cfg)  │ Engine    │ Renderer  │  (cURL)       │
├─────────────┼───────────┼───────────┼───────────────┤
│ ConfigLoader│ Parser    │FieldFactory│ AiRequest     │
│ ConfigValid.│ VarModif. │ HandlerReg│ AiResponse    │
├─────────────┼───────────┼───────────┼───────────────┤
│ blocks.json │ *.tpl     │ Handlers  │ Endpoints     │
│ layouts.json│ (views)   │ (Strategy)│ (Registry)    │
│ generators  │           │           │               │
├─────────────┴───────────┴───────────┼───────────────┤
│           Security Layer            │  Asset Mgr    │
│      SignedParams (HMAC-SHA256)     │  (CSS/JS)     │
├─────────────────────────────────────┼───────────────┤
│         SSE Streaming (volitelné)   │  ErrorHandler │
│   SseStream / SseEvent / Progress   │  (globální)   │
└─────────────────────────────────────┴───────────────┘
```

**Návrhové vzory použité v projektu:**
- **Builder** — `PlatformBridgeBuilder` pro konfiguraci instance
- **Facade** — `PlatformBridge` jako jediný vstupní bod
- **Strategy** — `FieldHandler` → různé handlery pro různé typy polí
- **Factory** — `FieldFactory`, `FormElementFactory` pro vytváření elementů
- **Registry** — `HandlerRegistry`, `EndpointRegistry` pro registraci komponent
- **Value Object** — `PlatformBridgeConfig`, `AiResponse`, `SseEvent`
- **Template Method** — `FieldConfigurator` jako abstraktní báze handlerů

---

## ⚙ Konfigurace

### Builder pattern

`PlatformBridgeBuilder` poskytuje fluent API pro konfiguraci:

| Metoda | Popis | Výchozí |
|---|---|---|
| `withConfigPath(string)` | Cesta ke složce s JSON konfiguracemi | `resources/config/defaults` |
| `withViewsPath(string)` | Cesta k šablonám (.tpl) | `resources/views` |
| `withCachePath(string)` | Cesta ke cache adresáři | `var/cache` |
| `withTranslationsPath(string)` | Cesta k překladům | `resources/translations` |
| `withLocale(string)` | Jazyk aplikace | `'cs'` |
| `withBridgeConfigPath(string)` | Cesta k `bridge-config.php` | `resources/config/bridge-config.php` |
| `withSecretKey(bool)` | Zapne/vypne HMAC podepisování | `false` |
| `withParamsTtl(int)` | TTL podepsaných parametrů (sekundy) | `null` (z configu) |
| `build()` | Sestaví instanci `PlatformBridge` | — |

### JSON konfigurace

Konfigurace je rozdělena do 3 souborů ve složce `resources/config/defaults/`:

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
            "label": "Text language variant",
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
            "label": "Tón",
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

**Klíčové atributy bloku:**

| Klíč | Typ | Popis |
|---|---|---|
| `id` | string | Unikátní identifikátor bloku |
| `name` | string | HTML atribut `name` |
| `ai_key` | string | Klíč odesílaný do AI API |
| `component` | string | Typ komponenty: `input`, `select`, `textarea` |
| `variant` | string | Varianta: `text`, `radio`, `checkbox`, `hidden`, `email`... |
| `label` | string | Popisek pole |
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

Soubor `resources/config/bridge-config.php` obsahuje citlivé nastavení:

```php
<?php
if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

return [
    'secretKey' => 'put-your-long-super-secret-key-here-32chars-minimum',
    'ttl' => 3600,   // Expirace podepsaných parametrů (sekundy)
];
```

> ⚠️ **Secret key musí mít minimálně 32 znaků.** Pro generování použijte:
> ```php
> echo \Zoom\PlatformBridge\Security\SignedParams::generateSecretKey();
> ```

---

## 🧩 Hlavní třídy

### PlatformBridge (fasáda)

**Namespace:** `Zoom\PlatformBridge\PlatformBridge`

Vstupní bod celé knihovny. Zapouzdřuje všechny interní komponenty.

```php
// Vytvoření instance
$bridge = PlatformBridge::create()->build();     // Builder
$bridge = PlatformBridge::createDefault();        // Výchozí konfigurace

// Hlavní API
$html = $bridge->renderFullForm('subject', [...]);  // Kompletní formulář + assety
$gen  = $bridge->getGenerator('subject');           // Info o generátoru
$html = $bridge->getAssets();                       // Pouze CSS/JS tagy

// Přístup k interním komponentám
$engine = $bridge->getTemplateEngine();             // Template engine
$config = $bridge->getConfig();                     // Konfigurace
```

**Metoda `renderFullForm()`** — hlavní flow:
1. Extrahuje `inject` hodnoty z parametrů
2. Zavolá `FormRenderer::build()` pro sestavení sekcí
3. Sestaví parametry (endpoint, request_amount, ...)
4. Podepíše parametry přes HMAC (pokud aktivní)
5. Renderuje Wrapper šablonu
6. Připojí CSS/JS assety

---

### ConfigManager

**Namespace:** `Zoom\PlatformBridge\Config\ConfigManager`

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

---

### Template Engine

**Namespace:** `Zoom\PlatformBridge\Template\Engine`

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

**Namespace:** `Zoom\PlatformBridge\Form\Form`

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

---

### HandlerRegistry

**Namespace:** `Zoom\PlatformBridge\Handler\HandlerRegistry`

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

**Namespace:** `Zoom\PlatformBridge\AI\AiClient`

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

**Namespace:** `Zoom\PlatformBridge\Security\SignedParams`

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

**Namespace:** `Zoom\PlatformBridge\Asset\AssetManager`

Správce CSS/JS assetů s logikou "vložit pouze jednou" (once pattern).

```php
// Automaticky se používá v renderFullForm()
$assets = $bridge->getAssets();  // <link> + <script> tagy

// Manuální použití
$manager = new AssetManager('assets/dist/serve.php');
$css = $manager->getStyles();    // <link rel="stylesheet">
$js  = $manager->getScripts();   // <script src="...">
$all = $manager->getAssets();    // Oba najednou

// Druhé volání vrátí prázdný string (already rendered)
$css2 = $manager->getStyles();   // ''

// Force reload
$css3 = $manager->getStyles(force: true);  // <link> znovu
```

---

### ErrorHandler

**Namespace:** `Zoom\PlatformBridge\Error\ErrorHandler`

Globální error handler s vizuálním výstupem pro vývoj.

```php
$handler = new ErrorHandler(showDetails: true);  // Dev mód
$handler = new ErrorHandler(showDetails: false); // Produkce

$handler->register();  // Registruje exception, error a shutdown handlery
```

**Zachytává:**
- Nezachycené výjimky (`set_exception_handler`)
- PHP chyby (`set_error_handler`) — konvertuje na `ErrorException`
- Fatální chyby (`register_shutdown_function`)

> ⚠️ V produkci nastavte `showDetails: false` — skryje stack trace a zobrazí generickou chybovou zprávu.

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
| `{_tran k='key' d='default'}` | Překlad | `{_tran k='btn.save' d='Save'}` |
| `{* komentář *}` | Komentář (odstraní se) | |

### Bezpečnost šablon

Engine obsahuje **blacklist nebezpečných PHP funkcí** (`exec`, `shell_exec`, `eval`, `file_get_contents`, `unlink`, ...). Pokus o jejich volání přes `{function="..."}` bude zablokován.

---

## 🎛 Handlery formulářových polí

Systém používá **Strategy pattern** — každý typ pole má vlastní handler.

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
use Zoom\PlatformBridge\Handler\Fields\FieldConfigurator;

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

### Architektura endpointů

```
AI/
├── AiClient.php           # HTTP klient (cURL)
├── AiClientConfig.php     # Konfigurace klienta
├── AiRequest.php          # Request builder
├── AiResponse.php         # Response wrapper
├── AiResponseRenderer.php # Renderování odpovědí
├── AiException.php        # Typované výjimky
├── API/
│   ├── ApiHandler.php     # JSON endpoint handler
│   ├── SseApiHandler.php  # SSE streaming handler
│   ├── EndpointDefinition.php # Abstraktní endpoint (uživatel dědí)
│   └── EndpointRegistry.php # Registr endpointů (z bridge-config.php)
└── SSE/
    ├── SseStream.php      # SSE output stream
    ├── SseEvent.php       # SSE event value object
    ├── SseProgress.php    # Progress tracking
    ├── SsePhase.php       # Fáze zpracování (enum)
    └── ParallelRequestManager.php  # curl_multi
```

### Flow API požadavku

1. Frontend odešle formulářová data (JSON)
2. `ApiHandler` / `SseApiHandler` zpracuje vstup
3. Ověří HMAC podpis (`SignedParams::verify()`)
4. Najde endpoint v `EndpointRegistry`
5. Sestaví `AiRequest` z formulářových dat
6. `AiClient` odešle cURL request na AI API
7. `AiResponse` parsuje odpověď
8. `AiResponseRenderer` renderuje HTML přes šablony
9. Vrátí JSON / SSE stream na frontend

### Typy odpovědí endpointů

| Typ | Popis | Příklad |
|---|---|---|
| `string` | Jednoduchý textový výsledek | Překlad textu |
| `array` | Pole klíč-hodnota | Email subject varianty |
| `nested` | Vnořená struktura | Komplexní výsledky |

---

## 📡 SSE Streaming

Server-Sent Events pro real-time progress reporting (připraveno, aktuálně zakomentováno).

### Fáze zpracování (`SsePhase`)

| Fáze | Popis |
|---|---|
| `INIT` | Inicializace |
| `VALIDATING` | Validace vstupu |
| `PREPARING` | Příprava požadavku |
| `SENDING` | Odesílání na API |
| `PROCESSING` | Zpracování odpovědi |
| `RENDERING` | Renderování výsledku |
| `COMPLETE` | Dokončeno |
| `ERROR` | Chyba |

### Paralelní požadavky

`ParallelRequestManager` podporuje 1–10 současných cURL požadavků přes `curl_multi_*`. Jakmile jeden doběhne, okamžitě se zavolá callback a odešle se SSE event.

---

## 💻 Frontend (TypeScript)

### Architektura

```
assets/src/ts/
├── main.ts              # Entry point
├── app.ts               # Hlavní Application třída
├── Core/                # EventBus, Constants
├── services/            # ApiClient, SessionManager, ErrorHandler
├── features/            # FormValidator, ResultActionHandler, TypedResponseRenderer
└── ui/                  # LoadingManager (+ zakomentovaný ProgressLoader)
```

### Flow formuláře

1. **Klik na Generate** → `FormValidator` validuje vstupy
2. **Extrakce dat** → Sbírá data z formuláře
3. **API volání** → `ApiClient` odešle POST
4. **Zobrazení výsledku** → `TypedResponseRenderer` vloží HTML
5. **Akce** → `ResultActionHandler` zajišťuje kopírování, smazání, regeneraci

---

## 🔧 Build systém

Projekt používá **esbuild** pro ultra-rychlý build TypeScript i SCSS.

### Konfigurace (`build.mjs`)

```javascript
// TypeScript → assets/dist/js/main.js
// SCSS → assets/dist/css/main.css
// Oboje: bundled, minified, bez sourcemap
```

### Příkazy

```bash
npm run dev    # Watch mode (automatický rebuild při změnách)
npm run build  # Jednorázový build
```

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

### 8. ErrorHandler — přepněte v produkci
```php
new ErrorHandler(showDetails: false);  // Skryje stack trace
```

---

## ⚠️ Upozornění

### 1. Secret key minimální délka
Secret key pro HMAC **musí mít minimálně 32 znaků**. Kratší klíč vyhodí `InvalidArgumentException`.

### 2. `bridge-config.php` nesmí být veřejně přístupný
Soubor obsahuje secret key — ujistěte se, že není v public webroot nebo je chráněný `.htaccess`.

### 3. Template engine cache — rekompilace
**V aktuální verzi se šablony rekompilují při KAŽDÉM požadavku** (optimalizace pro vývoj). TODO v kódu naznačuje, že kontrola `filemtime()` bude odkomentována pro produkci.

```php
// TODO: Uncomment this
// if (!file_exists($cachePath) || (filemtime($cachePath) < filemtime($templatePath))) {
$this->cacheCompiledTemplate();  // Vždy se překompiluje
// }
```

### 4. SSE streaming je zakomentovaný
Frontend i backend podpora SSE je kompletně připravená, ale zakomentovaná. Aktivace vyžaduje odkomentování bloků v:
- `assets/src/ts/app.ts` (SseClient, ProgressLoader)
- Nastavení `options.enableSse: true`

### 5. Translator je zakomentovaný
Systém překladů (`Translator`) je připravený, ale neaktivní. Odkomentujte v `PlatformBridge::boot()` a `PlatformBridgeBuilder`.

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
Frontend JS to zpracuje automaticky — žádný vlastní kód není potřeba.

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
php -r "require 'vendor/autoload.php'; echo \Zoom\PlatformBridge\Security\SignedParams::generateSecretKey();"
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

Builder automaticky fallbackuje na `resources/` uvnitř balíčku. Pro override stačí zavolat příslušnou `with*()` metodu:

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

### 10. Přidání vlastního endpointu

Endpointy se definují **deklarativně** jako pole v `bridge-config.php` — není potřeba vytvářet vlastní PHP třídy:

```php
return [
    'api_key'   => '...',
    'base_url'  => '...',
    'endpoints' => [
        'CreateSubject' => [
            'generator_id'  => 'subject',        // ID generátoru v generators.json
            'response_type' => 'nested',          // 'string' | 'array' | 'nested'
            'template'      => '/Components/NestedResult',
            'variant_key'   => 'topic_source',    // Klíč pro detekci varianty (null = bez variant)
            'variants'      => [                  // Deklarativní pravidla dle varianty
                'template' => [
                    'remove_fields' => ['email_topic', 'topic_source'],
                ],
                'custom' => [
                    'remove_fields' => ['template_id', 'topic_source'],
                ],
            ],
        ],
        'GenerateText' => [
            'generator_id'  => 'text',
            'response_type' => 'string',
            'template'      => '/Components/SingleKeyResult',
        ],
    ],
];
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

## 📁 Přehled souborů projektu

| Soubor / Složka | Popis |
|---|---|
| `index.php` | Entry point — vrací FQCN třídy pro autoload |
| `demo.php` | Demo skript pro lokální testování |
| `composer.json` | PHP závislosti a autoload konfigurace |
| `package.json` | Node.js závislosti pro frontend build |
| `build.mjs` | Konfigurace esbuild (TS + SCSS) |
| `src/PlatformBridge/` | Kompletní PHP zdrojový kód knihovny |
| `resources/config/` | JSON konfigurace + bridge config |
| `resources/views/` | Šablony (.tpl) |
| `assets/src/` | TypeScript + SCSS zdrojové soubory |
| `assets/dist/` | Zkompilované CSS/JS |
| `var/cache/` | Cache zkompilovaných šablon |
| `docs/` | Dokumentace |

---

> **Verze dokumentace:** 1.0.0 | **Poslední aktualizace:** Únor 2026
