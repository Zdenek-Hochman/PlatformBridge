# 📋 PlatformBridge — Kompletní soupis projektu

> **Verze:** 1.0.0
> **Autor:** Zdeněk Hochman (virtualzoom.com)
> **Licence:** Proprietary
> **Datum dokumentu:** 9. března 2026
> **Repozitář:** `http://git.virtualzoom.com:3000/VIRTUALZOOM/ZoomPlatformBridge.git`

---

## 1. 📝 Popis aplikace

**PlatformBridge** je middleware platforma, která propojuje interní aplikace společnosti VirtualZoom s AI API. Hlavní účel je umožnit uživatelům (marketéři, copywriteři, správci obsahu) interaktivně generovat textový obsah pomocí umělé inteligence — například předměty e-mailů, marketingové texty, popisy produktů a podobně.

Aplikace funguje jako **samostatná PHP knihovna** (Composer balíček), kterou lze snadno integrovat do jakékoliv existující webové aplikace. Stačí ji nainstalovat, nakonfigurovat a zavolat jednu metodu — knihovna sama vygeneruje kompletní formulář s uživatelským rozhraním, odešle data na AI API a výsledky zobrazí uživateli.

### Jak to funguje z pohledu uživatele:
1. Uživatel otevře stránku s formulářem (formulář se generuje automaticky z JSON konfigurace).
2. Vyplní požadované parametry — jazyk, tón komunikace, téma, klíčová slova apod.
3. Klikne na tlačítko „Generovat".
4. Systém odešle data na AI API, které vrátí vygenerovaný obsah.
5. Výsledky se zobrazí přímo pod formulářem — uživatel je může zkopírovat, znovu vygenerovat jednotlivé části nebo ohodnotit.

### Klíčové vlastnosti:
- **Dynamické formuláře** — formuláře se skládají z JSON konfigurace, není nutné psát HTML ručně
- **Grid layout** — formuláře podporují víceřádkové a vícesloupcové rozložení
- **Komunikace s AI** — integrovaný klient pro REST API s podporou více transportních protokolů
- **Bezpečnost** — HMAC podepisování parametrů proti manipulaci s daty
- **Modulární architektura** — každá část systému je nezávislá a rozšiřitelná
- **SSE streaming** — podpora průběžného zobrazování výsledků v reálném čase
- **Session management** — možnost regenerovat jednotlivé části výsledku bez opětovného odesílání formuláře

---

## 2. ⚙️ Technologie a závislosti

### Backend (PHP)

| Technologie | Verze | Účel |
|---|---|---|
| **PHP** | >= 8.1 | Hlavní serverový jazyk |
| **Composer** | >= 2.0 | Správa PHP závislostí |
| **ext-curl** | * | HTTP komunikace s AI API |
| **ext-json** | * | Parsování JSON dat |

> Knihovna **nemá žádné externí PHP závislosti** (žádný Laravel, Symfony apod.) — je kompletně soběstačná.

### Frontend (TypeScript / SCSS)

| Technologie | Verze | Účel |
|---|---|---|
| **TypeScript** | ^5.9.3 | Typově bezpečný JavaScript |
| **esbuild** | ^0.27.3 | Ultra-rychlý bundler (kompilace TS + SCSS) |
| **esbuild-sass-plugin** | ^3.6.0 | SCSS kompilace v rámci esbuild |

> Frontend rovněž **nemá žádné runtime závislosti** — žádný React, Vue, jQuery. Vše je napsáno od nuly ve vanilla TypeScript.

### Build systém

Projekt používá **esbuild** jako build nástroj. Vstupní soubory:
- `assets/src/ts/main.ts` → `assets/dist/js/main.js`
- `assets/src/scss/main.scss` → `assets/dist/css/main.css`

Příkazy:
- `npm run dev` — watch mode (automatická rekompilace při změnách)
- `npm run build` — produkční build (minifikovaný)

---

## 3. 🔧 Backend — Soupis komponent

### 3.1 PlatformBridge (Hlavní fasáda)

**Co to dělá:** Vstupní bod celé knihovny. Jediná třída, se kterou pracuje vývojář integrující knihovnu do své aplikace.

**Jak se používá:**
- Vytvoření instance přes Builder pattern (`PlatformBridge::create()->...->build()`)
- Zavolání `renderFullForm('subject')` vrátí kompletní HTML s formulářem, styly a skripty
- Všechny interní komponenty se inicializují automaticky

**Podřízené komponenty:**
- ConfigManager, Template Engine, HandlerRegistry, FieldFactory, FormRenderer, AssetManager, SignedParams, ErrorHandler

**🔮 Možná vylepšení do budoucna:**
- Podpora pluginového systému — registrace vlastních rozšíření přes builder
- Lazy loading komponent — inicializovat jen to, co se skutečně použije
- Event hooky — možnost reagovat na lifecycle události (before render, after render)

---

### 3.2 PlatformBridgeBuilder & PlatformBridgeConfig

**Co to dělá:** Builder umožňuje pohodlnou konfiguraci knihovny krok za krokem. Config je neměnný objekt (immutable value object) uchovávající všechna nastavení.

**Konfigurovatelné parametry:**
| Parametr | Popis | Výchozí hodnota |
|---|---|---|
| ConfigPath | Cesta ke složce s JSON konfiguracemi | `resources/config/defaults` |
| ViewsPath | Cesta k šablonám (.tpl) | `resources/views` |
| CachePath | Cesta ke cache šablon | `var/cache` |
| TranslationsPath | Cesta k překladům *(zatím neaktivní)* | `resources/translations` |
| Locale | Jazyk aplikace | `cs` |
| SecretKey | Zapnutí HMAC podepisování | `false` |
| ParamsTtl | Expirace podepsaných parametrů (sekundy) | `null` (z configu) |

**🔮 Možná vylepšení do budoucna:**
- Aktivovat překladový systém (Translator) — podporovat vícejazyčné rozhraní
- Podpora pro environment proměnné — automatické načtení konfigurace z `.env` souboru
- Validace konfigurace s detailními chybovými hláškami

---

### 3.3 Konfigurační systém (ConfigManager, ConfigLoader, ConfigValidator, ConfigResolver)

**Co to dělá:** Načítá, validuje a zpracovává JSON konfigurační soubory, které definují strukturu formulářů.

**Tři konfigurační soubory:**

| Soubor | Účel |
|---|---|
| **blocks.json** | Definice jednotlivých formulářových polí (vstupní pole, selecty, radio buttony, textareas...) |
| **layouts.json** | Rozložení polí na stránce — sekce, sloupce, grid layout |
| **generators.json** | Propojení layoutu s konkrétním AI endpointem — „generátor" = jeden typ úlohy |

**Jak to funguje:**
1. **ConfigLoader** načte JSON soubory z disku
2. **ConfigValidator** ověří strukturu dat (jsou přítomné povinné klíče? existují reference na bloky?)
3. **ConfigResolver** rozbalí reference — layout odkazuje na bloky, generátor odkazuje na layout
4. **ConfigManager** koordinuje celý proces a poskytuje API pro čtení dat

**🔮 Možná vylepšení do budoucna:**
- **Webový editor konfigurace** — GUI pro vizuální úpravu bloků a layoutů bez editování JSON
- Podpora více generátorů z jedné konfigurace (momentálně je registrován jeden: `CreateSubject`)
- Cachování rozřešených konfigurací do souboru (nyní se řeší při každém požadavku)
- Dynamické načítání konfigurace z databáze (namísto statických JSON souborů)
- Verzování konfigurací — historie změn a rollback

---

### 3.4 Šablonovací engine (Template Engine + Parser)

**Co to dělá:** Vlastní šablonovací systém s kompilací šablon do PHP kódu a cachováním. Šablony používají Smarty-like syntaxi.

**Funkce:**
- Kompiluje `.tpl` šablony do PHP a ukládá do cache
- Podpora proměnných, smyček, podmínek
- Automatická rekompilace při změně šablony
- Bezpečnostní blacklist nebezpečných funkcí

**Existující šablony:**

| Šablona | Účel |
|---|---|
| `Atoms/Wrapper.tpl` | Hlavní obalový element celého formuláře |
| `Atoms/Label.tpl` | Popisek formulářového pole |
| `Atoms/Small.tpl` | Doplňkový text pod polem |
| `Element/Input.tpl` | HTML input element |
| `Element/Select.tpl` | HTML select element |
| `Element/Textarea.tpl` | HTML textarea element |
| `Element/TickBox.tpl` | Speciální checkbox/toggle |
| `Components/Handlers.tpl` | Ovládací tlačítka ve výsledcích |
| `Components/Icons.tpl` | SVG ikony |
| `Components/NestedResult.tpl` | Výsledek s vnitřní strukturou (klíč–hodnota) |
| `Components/SingleKeyResult.tpl` | Výsledek pro jednu regenerovanou hodnotu |

**🔮 Možná vylepšení do budoucna:**
- Podpora dědičnosti šablon (extends/block)
- Podpora filtrů a modifikátorů proměnných (uppercase, truncate apod.)
- Možnost overridu šablon z hostitelské aplikace (custom views path)

---

### 3.5 Formulářový systém (Form, FieldFactory, HandlerRegistry, FieldHandlers)

**Co to dělá:** Automaticky generuje HTML formuláře z JSON konfigurace. Každý typ formulářového pole má vlastní „handler" — třídu, která ví, jak daný typ vykreslit.

**Dostupné handlery (typy polí):**

| Handler | Generuje |
|---|---|
| TextHandler | Textový input (`<input type="text">`) |
| NumberHandler | Číselný input (`<input type="number">`) |
| DateHandler | Datový vstup |
| HiddenHandler | Skryté pole (`<input type="hidden">`) |
| SelectHandler | Rozbalovací nabídka (`<select>`) |
| TextareaHandler | Víceřádkový textový vstup (`<textarea>`) |
| RadioHandler | Skupina radio buttonů |
| CheckboxHandler | Zaškrtávací pole |
| TickBoxHandler | Speciální toggle přepínač |
| FileHandler | Nahrávání souborů |

**Princip:**
1. **FieldFactory** dostane konfigurační blok (z JSON)
2. Zeptá se **HandlerRegistry** — „kdo umí obsloužit tento typ pole?"
3. Registry projde registrované handlery a najde vhodný
4. Handler vytvoří formulářový element a přidá ho do **Form** bufferu
5. Na konci se celý buffer vyrenderuje přes šablonovací engine

**🔮 Možná vylepšení do budoucna:**
- **Nové typy polí** — color picker, range slider, WYSIWYG editor, datepicker s kalendářem
- Vlastní handlery z hostitelské aplikace (registrace přes builder)
- Drag & drop řazení polí
- Podmíněné zobrazování polí na backendu (nejen na frontendu)

---

### 3.6 Runtime (FormRenderer, LayoutManager)

**Co to dělá:** Sestavuje kompletní HTML výstup formuláře — řeší rozložení polí do sekcí a CSS Grid layout.

**FormRenderer:**
- Vezme ID generátoru, načte jeho layout a sekce
- Pro každý blok v sekci zavolá FieldFactory
- Aplikuje dynamické hodnoty z kontextu (inject)
- Vrátí pole HTML sekcí

**LayoutManager:**
- Obaluje bloky do layout wrapperů s data atributy
- Podporuje: span (sloupcový), row_span (řádkový), grid_column, grid_row
- Podmíněná viditelnost (`data-visible-if`)

**🔮 Možná vylepšení do budoucna:**
- Responzivní breakpointy — různý layout pro desktop/tablet/mobil
- Podpora záložek (tabs) a akordeonů (accordion) pro sekce
- Drag & drop přeuspořádání sekcí za běhu

---

### 3.7 AI vrstva (AiClient, AiRequest, AiResponse, AiResponseRenderer)

**Co to dělá:** Komunikace s externím AI API přes HTTP (cURL). Odesílá uživatelské vstupy a přijímá vygenerovaný obsah.

**Komponenty:**

| Třída | Účel |
|---|---|
| **AiClient** | HTTP klient odesílající požadavky na AI API přes cURL |
| **AiClientConfig** | Konfigurace klienta — API klíč, URL, timeout, retry |
| **AiRequest** | Objekt reprezentující jeden požadavek (endpoint, data, headers) |
| **AiResponse** | Strukturovaná odpověď z API s přístupem přes tečkovou notaci |
| **AiResponseRenderer** | Renderování odpovědi do HTML přes šablonovací engine |
| **AiException** | Strukturované výjimky (timeout, neplatný request, chyba spojení...) |

**Nastavení API:**
- Výchozí API URL: `https://api.virtualzoom.com/v2/AI`
- Autentizace: Bearer token (API key)
- Timeout: 30 sekund (konfigurovatelné)
- Retry: až 3 pokusy

**🔮 Možná vylepšení do budoucna:**
- **Podpora více AI providerů** — OpenAI, Anthropic, Google Gemini (abstraktní vrstva)
- Rate limiting a throttling
- Request queueing — fronta požadavků s prioritami
- Cachování AI odpovědí na backendu (aby se stejný dotaz neposílal 2×)
- Webhooky — notifikace po dokončení dlouhého generování
- Logování a analytics — sledování spotřeby tokenů, úspěšnosti generování

---

### 3.8 API Handler a Endpoint systém

**Co to dělá:** Serverový endpoint, který přijímá AJAX požadavky z frontendu, ověřuje bezpečnost, deleguje na správný AI endpoint a vrací výsledky.

**Průběh zpracování požadavku:**
1. Přijme JSON vstup z frontendu
2. Ověří HMAC podpis (bezpečnost)
3. Načte konfiguraci endpointu z registru
4. Detekuje single-key mód (regenerace jedné části)
5. Sestaví AiRequest přes EndpointDefinition
6. Odešle přes AiClient na AI API
7. Parsuje a renderuje odpověď
8. Vrátí strukturovaný JSON s HTML výstupem

**EndpointDefinition (abstraktní třída):**
- Každý typ úlohy (generování předmětů, textů...) má vlastní definici
- Definuje: název endpointu, typ odpovědi, šablonu pro renderování
- Podpora variant (template vs. custom vstup)
- Single-key mód pro regeneraci jedné hodnoty

**Registr endpointů:**
- `CreateSubject` — generování e-mailových předmětů (momentálně jediný registrovaný)
- Adresářová struktura pro budoucí endpointy: `Endpoints/ZD/`, `Endpoints/ZL/`, `Endpoints/ZV/`

**🔮 Možná vylepšení do budoucna:**
- **Přidání dalších endpointů** — texty e-mailů, popisy produktů, sociální média, chatbot odpovědi
- Middleware pipeline na backendu (logování, validace, transformace)
- Batching — odeslání více požadavků najednou
- SSE streaming na backend straně (průběžné odesílání výsledků)
- Webhook systém — asynchronní notifikace o dokončení

---

### 3.9 Bezpečnostní vrstva (SignedParams, SecurityException)

**Co to dělá:** Chrání komunikaci mezi frontendem a backendem proti neoprávněné manipulaci s daty.

**Funkce:**
- **HMAC-SHA256 podepisování** — veškeré parametry (endpoint, API klíče, konfigurace) jsou podepsány tajným klíčem
- **TTL (Time-To-Live)** — podepsané parametry mají omezenou platnost (ochrana proti replay útokům)
- **Base64url kódování** — bezpečný přenos přes HTTP
- **Minimální délka klíče** — 32 znaků (256 bitů)

**Konfigurace** se nachází v `resources/config/bridge-config.php` — soubor musí být chráněn před přímým přístupem z webu.

**🔮 Možná vylepšení do budoucna:**
- CSRF token ochrana
- Rate limiting na API endpointu
- IP whitelist/blacklist
- Audit log — záznam všech bezpečnostních událostí
- Rotace tajných klíčů bez výpadku služby

---

### 3.10 Asset Manager

**Co to dělá:** Spravuje CSS a JavaScript soubory potřebné pro fungování frontendu. Zajišťuje, že se assety vloží do stránky pouze jednou (i při více formulářích na stránce).

**Generuje:**
- `<link rel="stylesheet" href="...main.css">`
- `<script src="...main.js"></script>`

**🔮 Možná vylepšení do budoucna:**
- Verzování assetů (cache busting přes hash)
- CDN podpora
- Inline critical CSS pro rychlejší loading
- Lazy loading JS — načítat až při interakci s formulářem

---

### 3.11 Error Handler

**Co to dělá:** Globální zachycení PHP chyb a výjimek. Zobrazuje přehledné chybové stránky s podrobnostmi (v debug režimu) nebo generickou zprávu (v produkci).

**Zachycuje:**
- Výjimky (Exception)
- PHP chyby (Error, Warning, Notice)
- Fatální chyby (Shutdown handler)

**🔮 Možná vylepšení do budoucna:**
- Integrace s loggovacím systémem (Monolog, Sentry)
- E-mailové notifikace o chybách
- Strukturované logování do souboru/databáze

---

## 4. 🖥️ Frontend — Soupis komponent

### 4.1 App (Hlavní třída aplikace)

**Co to dělá:** Vstupní bod frontendu. Inicializuje všechny komponenty v definovaném pořadí (pipeline).

**Inicializační pipeline:**
1. **DOM** — inicializace custom selectů a layout controlleru
2. **Core** — vytvoření EventBus instance
3. **Services** — nastavení API klienta, transportů, middleware
4. **Features** — inicializace validátoru formuláře
5. **Bindings** — napojení event listenerů (kliknutí na „Generovat")

---

### 4.2 EventBus (Pub/Sub systém)

**Co to dělá:** Centrální komunikační kanál pro všechny komponenty. Umožňuje volné propojení — komponenty se neznají navzájem, komunikují přes události.

**Podporované události:**

| Událost | Kdy se vyvolá |
|---|---|
| `request:start` | Před odesláním požadavku |
| `request:end` | Po dokončení požadavku |
| `success` | Úspěšná odpověď z API |
| `error` | Chyba při komunikaci |
| `validation` | Chyba validace formuláře |
| `loading` | Změna loading stavu |
| `copy` | Zkopírování výsledku do schránky |
| `transport:fallback` | Přepnutí na záložní transport |
| `regenerate-key` | Regenerace jednoho výsledku |
| `sse:progress` | Průběh SSE streamingu |
| `sse:result` | Jeden výsledek z SSE streamu |
| `sse:complete` | Dokončení SSE streamu |
| `sse:error` | Chyba v SSE streamu |

**Klíčová vlastnost:** Události se propagují jako DOM CustomEvent s prefixem `ai:` — hostitelská aplikace může na ně reagovat přes `window.addEventListener('ai:success', ...)`.

**🔮 Možná vylepšení do budoucna:**
- Wildcard subscribers (`request:*`)
- Event replay — znovuodeslání posledních událostí pro pozdě připojené listenery
- Debug panel — vizuální zobrazení toku událostí

---

### 4.3 Dom utilita (jQuery-like helper)

**Co to dělá:** Lehká obálka nad nativním DOM API. Poskytuje pohodlné metody pro manipulaci s elementy (jako jQuery, ale bez závislosti).

**Funkce:**
- Vytváření elementů (`Dom.create`)
- Selekce elementů (`Dom.q`, `Dom.qa`, `Dom.component`, `Dom.action`)
- Fluent API na obalovacím objektu (`DomNode`): addClass, removeClass, attr, data, html, text, css, on, off, closest, findAll...
- Event delegation (`Dom.delegate`)
- XSS ochrana (`Dom.esc`)

---

### 4.4 ApiClient (Komunikace s backendem)

**Co to dělá:** Sjednocený přístupový bod pro odesílání dat na backend. Podporuje více transportních protokolů a middleware pipeline.

**Transporty:**

| Transport | Protokol | Stav | Popis |
|---|---|---|---|
| **HttpTransport** | HTTP POST (fetch) | ✅ Aktivní | Klasický požadavek s JSON odpovědí |
| **SseTransport** | Server-Sent Events | ✅ Aktivní | Průběžné streamování výsledků |
| **WebSocketTransport** | WebSocket | 🔜 Připraveno | Obousměrná real-time komunikace |

**Middleware pipeline (onion model):**

| Middleware | Funkce |
|---|---|
| **RetryMiddleware** | Automatické opakování při selhání (konfigurovatelný počet pokusů) |
| **CacheMiddleware** | Cachování odpovědí po nastavenou dobu (TTL) |
| **TimingMiddleware** | Logování doby trvání požadavků |

**Klíčové vlastnosti:**
- Fluent API: `api.registerTransport(...).use(...).use(...)`
- Automatický výběr transportu podle priority
- Vynucený transport: `api.via('http').send(data)`
- Fallback — pokud primární transport selže, zkusí se další

**🔮 Možná vylepšení do budoucna:**
- **Offline podpora** — cachovat data pro offline práci
- Queue middleware — fronta požadavků s debouncing
- Komprese payloadu
- Batching — sdružení více požadavků do jednoho
- GraphQL transport

---

### 4.5 FormValidator (Validace formuláře)

**Co to dělá:** Validuje povinná formulářová pole před odesláním. Zobrazuje vizuální chybové zprávy u nevalidních polí.

**Podporované typy validace:**
- Prázdné textové pole (input, textarea)
- Nevybraný select
- Nezaškrtnutý checkbox
- Nezvolená radio skupina

**Funkce:**
- Zvýraznění chybného pole CSS třídou
- Zobrazení chybové zprávy pod polem
- Focus na první chybné pole
- Live validace — odstranění chyby při vyplnění

**🔮 Možná vylepšení do budoucna:**
- Rozšířená validace — email, URL, regex pattern, min/max délka na frontendu
- Asynchronní validace (kontrola na serveru)
- Vlastní validační pravidla přes konfiguraci
- Podpora vícejazyčných chybových hlášek

---

### 4.6 VisibilityController (Podmíněné zobrazování)

**Co to dělá:** Řídí viditelnost formulářových bloků na základě aktuálního stavu jiných polí. Například: pole „Zadejte téma" se zobrazí pouze když je zvolen radio button „Vlastní téma".

**Jak to funguje:**
- Čte `data-visible-if` atribut z HTML (generovaný PHP backendem)
- Parsuje JSON podmínky
- Sleduje `change` eventy na formuláři
- Zobrazuje/skrývá bloky s CSS animací
- Logika: všechny podmínky musí být splněny (AND)

**Podporované typy zdrojových polí:**
- Radio buttony — podmínka na vybranou hodnotu
- Checkboxy — podmínka na stav checked/unchecked

**🔮 Možná vylepšení do budoucna:**
- Podpora OR logiky (zobrazit pokud ALESPOŇ jedna podmínka platí)
- Podmínky na textové hodnoty a selecty
- Kaskádové podmínky (pole A → pole B → pole C)
- Animace při zobrazení/skrytí (slide, fade)

---

### 4.7 ResultActionHandler (Akce ve výsledcích)

**Co to dělá:** Zpracovává uživatelské akce na vygenerovaných výsledcích — opakování generování, kopírování do schránky, hodnocení.

**Podporované akce:**

| Akce | Stav | Popis |
|---|---|---|
| **repeat** | ✅ Aktivní | Regenerace jednoho výsledku (single-key mód) |
| **copy** | ✅ Aktivní | Kopírování textu výsledku do schránky |
| **use** | 🔜 Připraveno | „Použít" výsledek v hostitelské aplikaci |
| **thumb-up / thumb-down** | 🔜 Připraveno | Hodnocení kvality výsledku |

**Princip regenerace (repeat):**
1. Data z posledního odeslání jsou uložena v SessionManager
2. Při kliknutí na „repeat" se data vezmou ze session + přidá se klíč pro regeneraci
3. Backend generuje pouze zadaný klíč (šetří AI tokeny)
4. Výsledek se aktualizuje přímo v DOM bez znovunačtení stránky

**🔮 Možná vylepšení do budoucna:**
- Funkční implementace tlačítka „Použít" — propojení s editorem hostitelské aplikace
- Implementace hodnocení (thumb-up/down) — zpětná vazba pro vylepšení AI
- Historie výsledků — uložení předchozích generování
- Porovnání výsledků — zobrazení více verzí vedle sebe
- Export výsledků (PDF, DOCX, clipboard s formátováním)

---

### 4.8 SessionManager (Správa relace)

**Co to dělá:** Uchovává data z posledního odeslání formuláře pro účely regenerace jednotlivých klíčů.

**Funkce:**
- Uložení formulářových dat po úspěšném odeslání
- Načtení dat pro repeat (regeneraci)
- Sestavení payloadu pro single-key mód
- Sledování stáří relace

**🔮 Možná vylepšení do budoucna:**
- Persistentní session (localStorage) — přežít refresh stránky
- Historie více relací
- TTL na session — automatická expirace
- Sdílení session mezi záložkami

---

### 4.9 CustomSelect (Stylizovatelný select)

**Co to dělá:** Převádí nativní HTML `<select>` na plně stylizovatelný custom element při zachování přístupnosti a funkcionality.

**Funkce:**
- Zachování nativního selectu (pro formuláře a přístupnost)
- Klávesová navigace (↑ ↓ Enter Escape)
- Synchronizace hodnoty s nativním selectem
- ARIA atributy pro screen readery
- Zavření při kliknutí mimo

**🔮 Možná vylepšení do budoucna:**
- Vyhledávání/filtrování v možnostech
- Multi-select
- Groupy (optgroup)
- Async loading možností (AJAX)

---

### 4.10 LayoutController (Grid layout na frontendu)

**Co to dělá:** Čte data atributy z HTML (generované backendem) a aplikuje CSS Grid layout na formulářové sekce a bloky.

**Podporované atributy:**
| Atribut | Úroveň | Popis |
|---|---|---|
| `data-layout-columns` | Sekce | Počet sloupců gridu |
| `data-layout-column-template` | Sekce | Custom `grid-template-columns` |
| `data-layout-span` | Blok | Sloupcový span |
| `data-layout-row-span` | Blok | Řádkový span |
| `data-layout-grid-column` | Blok | Explicitní `grid-column` |
| `data-layout-grid-row` | Blok | Explicitní `grid-row` |

**🔮 Možná vylepšení do budoucna:**
- Responzivní breakpointy (automatická úprava sloupců na mobilu)
- Přepínání mezi grid a flex layoutem
- Masonry layout podpora

---

### 4.11 ProgressLoader (Průběh streamingu)

**Co to dělá:** UI komponenta pro zobrazení průběhu SSE streamingu. Vizualizuje jednotlivé fáze generování s progress barem.

**Fáze zobrazení:**
1. ⚙️ Inicializace
2. 🔍 Ověřování
3. 📋 Příprava
4. 📡 Odesílání
5. 🤖 Zpracování
6. 🎨 Renderování
7. ✅ Hotovo

**Funkce:**
- Progress bar s počtem dokončených odpovědí
- Časovač
- Postupné zobrazování výsledků s animací
- Napojení na EventBus (reaguje na SSE eventy)

---

### 4.12 LoadingManager (Správa loading stavů)

**Co to dělá:** Řídí vizuální loading stav při odesílání formuláře — overlay, deaktivace polí, změna textu tlačítka.

**Funkce:**
- Loading třída na formulář
- Overlay s spinnerem
- Deaktivace všech formulářových prvků
- Změna textu tlačítka na „Generuji..."
- Automatické obnovení stavu po dokončení

---

## 5. 🗄️ Databáze a datové úložiště

### Aktuální stav

Projekt **v současné verzi nepoužívá databázi**. Veškerá konfigurace je uložena ve statických JSON souborech a PHP konfiguračních souborech. Session data se drží pouze v paměti prohlížeče (JavaScript).

### Datová úložiště v projektu

| Typ | Umístění | Účel |
|---|---|---|
| JSON konfigurace | `resources/config/defaults/*.json` | Definice formulářů |
| PHP config | `resources/config/bridge-config.php` | Secret key, TTL |
| Template cache | `var/cache/*.cache.php` | Zkompilované šablony |
| JS session | In-memory (prohlížeč) | Poslední odeslání formuláře |

### 🔮 Návrhy databázového řešení do budoucna

Pro rozšíření funkcionality by bylo vhodné zavést databázi. Doporučené schéma:

#### Tabulka `ai_generations` (historie generování)
- `id` — primární klíč
- `generator_id` — typ generátoru (subject, body...)
- `user_id` — kdo generoval
- `input_data` — vstupní data (JSON)
- `output_data` — výstupní data z AI (JSON)
- `tokens_used` — spotřebované tokeny
- `duration_ms` — doba generování
- `status` — úspěch/chyba
- `created_at` — čas

#### Tabulka `ai_feedback` (hodnocení výsledků)
- `id` — primární klíč
- `generation_id` — FK na ai_generations
- `result_key` — klíč výsledku (subject, preheader...)
- `rating` — thumb up/down
- `user_id` — kdo hodnotil
- `created_at` — čas

#### Tabulka `ai_configs` (dynamická konfigurace)
- `id` — primární klíč
- `generator_id` — ID generátoru
- `config_type` — blocks/layouts/generators
- `config_data` — JSON konfigurace
- `version` — verze konfigurace
- `is_active` — aktuálně platná verze
- `created_by` — kdo vytvořil
- `created_at` — čas

#### Tabulka `ai_usage_stats` (statistiky využití)
- `id` — primární klíč
- `user_id` — uživatel
- `endpoint` — AI endpoint
- `tokens_in` / `tokens_out` — spotřeba tokenů
- `cost_estimate` — odhadovaná cena
- `date` — den

#### Tabulka `ai_api_keys` (správa API klíčů)
- `id` — primární klíč
- `name` — název klíče
- `api_key_hash` — hashovaný API klíč
- `rate_limit` — limit požadavků
- `is_active` — aktivní/neaktivní
- `expires_at` — expirace

---

## 6. 📁 Přehled adresářové struktury

```
PlatformBridge/
│
├── 📂 assets/                    Frontend zdrojové soubory
│   ├── 📂 dist/                  Zkompilované výstupy (JS, CSS)
│   └── 📂 src/
│       ├── 📂 scss/              Styly (SCSS)
│       │   ├── base/             Reset, proměnné, typografie
│       │   ├── common/           Sdílené styly
│       │   ├── components/       Styly komponent
│       │   └── layout/           Grid a layout styly
│       └── 📂 ts/                TypeScript zdrojový kód
│           ├── Const/            Konstanty
│           ├── Core/             EventBus, Dom utilita
│           ├── Features/         FormValidator, VisibilityController, ResultActionHandler
│           ├── Middleware/       RetryMiddleware, CacheMiddleware, TimingMiddleware
│           ├── Services/         ApiClient, SessionManager, LoadingManager, Transports
│           ├── Types/            TypeScript typy a rozhraní
│           ├── UI/               CustomSelect, LayoutController, ProgressLoader
│           └── Utils/            Pomocné utility
│
├── 📂 docs/                      Dokumentace
│
├── 📂 resources/                 Konfigurace a šablony
│   ├── 📂 config/
│   │   ├── bridge-config.php     Secret key, TTL
│   │   └── 📂 defaults/
│   │       ├── blocks.json       Definice formulářových polí
│   │       ├── generators.json   Definice generátorů (vazba layout → endpoint)
│   │       └── layouts.json      Rozložení polí (grid layout)
│   └── 📂 views/                 TPL šablony
│       ├── Atoms/                Atomické komponenty (Wrapper, Label, Small)
│       ├── Components/           Složené komponenty (výsledky, ikony, handlery)
│       └── Element/              Formulářové elementy (Input, Select, Textarea, TickBox)
│
├── 📂 src/PlatformBridge/        PHP zdrojový kód (backend)
│   ├── AI/                       AI komunikační vrstva
│   │   ├── API/                  API handler, EndpointRegistry, EndpointDefinition
│   │   │   └── Endpoints/        Konkrétní implementace endpointů (ZD, ZL, ZV)
│   │   └── TEST/                 Testovací mock endpointy
│   ├── Asset/                    Správa CSS/JS assetů
│   ├── Config/                   Konfigurační systém (Manager, Loader, Validator, Resolver)
│   ├── Error/                    Globální error handling
│   ├── Form/                     Formulářový systém
│   │   ├── Element/              Definice formulářových elementů (Input, Select, Textarea...)
│   │   └── Factory/              Factory pro vytváření elementů
│   ├── Handler/                  Handlery formulářových polí
│   │   └── Fields/               Konkrétní handlery (Text, Radio, Select, Checkbox...)
│   ├── Runtime/                  FormRenderer, LayoutManager
│   ├── Security/                 HMAC podepisování, SecurityException
│   ├── Template/                 Šablonovací engine (Engine, Parser)
│   └── Translator/               Překladový systém (připraveno, zatím neaktivní)
│
├── 📂 var/cache/                 Cache zkompilovaných šablon
├── 📂 vendor/                    Composer závislosti (autoloader)
│
├── build.mjs                     Build skript (esbuild)
├── composer.json                 PHP závislosti a autoloading
├── demo.php                      Demo / testovací skript
├── index.php                     Entry point (standalone / CLI)
├── package.json                  Frontend závislosti a build příkazy
└── tsconfig.json                 TypeScript konfigurace
```

---

## 7. 🔮 Souhrnný přehled vylepšení do budoucna

### Priorita VYSOKÁ (doporučeno brzy)
1. **Nové AI endpointy** — rozšířit portfolio generování (texty e-mailů, popisy produktů, sociální média)
2. **Databáze pro historii** — logování generování, spotřeby tokenů, zpětné vazby
3. **Aktivace překladového systému** — podpora EN/CZ a dalších jazyků
4. **Funkční hodnocení výsledků** (thumb up/down) — data pro vylepšení AI
5. **Produkční error logging** — integrace se Sentry nebo jiným systémem

### Priorita STŘEDNÍ (plánovat)
6. **Webový editor konfigurace** — vizuální úprava formulářů bez editování JSON
7. **Responzivní layout** — optimalizace pro mobil a tablet
8. **Cachování AI odpovědí** — úspora tokenů za opakované dotazy
9. **Rozšířená validace formuláře** — regex, async, custom pravidla
10. **Tlačítko "Použít"** — přímé propojení výsledku s cílovou aplikací
11. **Asset versioning** — cache busting pro CSS/JS

### Priorita NIŽŠÍ (nice to have)
12. **Offline podpora** — cachování v prohlížeči
13. **Drag & drop** editor formulářů
14. **WYSIWYG pole** pro rich text vstupy
15. **WebSocket transport** — real-time chat s AI
16. **Multi-provider AI** — přepínání mezi OpenAI, Anthropic, Gemini
17. **Export výsledků** do PDF/DOCX
18. **Analytics dashboard** — přehledy využití, náklady, úspěšnost

---

## 8. 🏗️ Návrhové vzory použité v projektu

| Vzor | Kde se používá | Účel |
|---|---|---|
| **Facade** | PlatformBridge | Jednoduchý vstupní bod pro celou knihovnu |
| **Builder** | PlatformBridgeBuilder | Fluent konfigurace instance |
| **Strategy** | FieldHandler + handlery | Různé strategie pro různé typy polí |
| **Factory** | FieldFactory, FormElementFactory | Vytváření objektů podle konfigurace |
| **Registry** | HandlerRegistry, EndpointRegistry | Dynamická registrace komponent |
| **Value Object** | PlatformBridgeConfig, AiResponse | Immutable datové objekty |
| **Template Method** | FieldConfigurator | Abstraktní báze s předdefinovaným chováním |
| **Observer/Pub-Sub** | EventBus | Volně vázaná komunikace mezi komponentami |
| **Middleware** | ApiClient pipeline | Rozšiřitelná pipeline pro zpracování požadavků |
| **Singleton** | EndpointRegistry | Globální přístup k registru endpointů |

---

*Dokument vygenerován z analýzy zdrojového kódu projektu PlatformBridge v1.0.0*
