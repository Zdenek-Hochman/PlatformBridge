# PlatformBridge — manažerský soupis projektu

> Datum: 9. března 2026  
> Verze projektu: 1.0.0  
> Typ řešení: interní AI middleware / knihovna pro generování formulářů a AI výstupů

---

## 1. Celkový popis aplikace

### Co je cílem aplikace
- PlatformBridge je mezivrstva mezi interní aplikací a AI službou.
- Slouží k tomu, aby se bez ručního programování každého formuláře dal rychle nasadit nový AI nástroj.
- Uživatel vyplní formulář, systém odešle vstupy do AI služby a vrátí hotové návrhy textů.
- Aktuálně je v projektu připravené zejména řešení pro generování e-mailových předmětů.

### Jak aplikace funguje v praxi
- Z konfigurace se automaticky složí formulář.
- Uživatel vyplní hodnoty jako jazyk, tón komunikace, typ kampaně nebo vstupní text.
- Backend doplní technické parametry, provede kontroly a odešle požadavek na AI API.
- Výstup se zobrazí přímo v rozhraní.
- Uživatel může výsledek zkopírovat nebo znovu vygenerovat jen konkrétní část.

### Pro koho je řešení vhodné
- marketingové týmy,
- copywriteři,
- správci kampaní,
- interní obsahové týmy,
- budoucí integrační projekty s více AI scénáři.

### Současný stav produktu
- Projekt je postavený jako znovupoužitelná knihovna v PHP.
- Má vlastní frontend v TypeScriptu a SCSS.
- Má vlastní šablonovací systém.
- Má bezpečnostní vrstvu pro podepisování parametrů.
- Má připravený základ pro streamované odpovědi, fallback komunikaci a budoucí real-time režimy.
- Aktivně nakonfigurovaný je jeden hlavní generátor: pokročilé generování e-mailových předmětů.

---

## 2. Hlavní byznysová hodnota

### Co projekt přináší firmě
- Zrychlení nasazení nových AI formulářů bez velkých zásahů do kódu.
- Jednotný způsob, jak propojit interní systém s AI API.
- Menší závislost na ruční přípravě HTML formulářů.
- Rychlejší testování nových use case scénářů.
- Lepší kontrolu nad tím, jaké vstupy se do AI posílají.
- Připravenost na škálování do dalších typů generátorů.

### Typické využití
- vytvořit formulář pro generování e-mailových předmětů,
- vytvořit formulář pro generování textu newsletteru,
- vytvořit formulář pro produktové popisy,
- vytvořit formulář pro varianty call-to-action,
- vytvořit formulář pro lokalizované texty ve více jazycích,
- vytvořit formulář pro regeneraci jen jedné části výsledku bez opakování celé úlohy.

---

## 3. Backend — soupis komponent a jejich funkce

## 3.1 Vstupní vrstva aplikace

### Komponenta: PlatformBridge
- **Úloha:** hlavní vstupní bod celé aplikace.
- **Co zajišťuje:** spojí konfiguraci, formuláře, šablony, bezpečnost a frontend assety do jednoho výstupu.
- **Co je k tomu potřeba:** cesty ke konfiguraci, šablonám, cache a případně bezpečnostnímu klíči.
- **Kontroly:** ověřuje dostupnost potřebných částí během startu.
- **Přínos pro provoz:** integrace do jiné aplikace je jednoduchá a jednotná.
- **Možné rozšíření:** přidat pluginový model, rozšíření přes moduly a životní cyklus událostí.

### Komponenta: Builder konfigurace
- **Úloha:** připraví instanci aplikace podle konkrétního prostředí.
- **Co zajišťuje:** nastavuje cesty, jazyk, cache, bezpečnostní režim a další provozní parametry.
- **Co je k tomu potřeba:** struktura projektu, konfigurační soubory a cache adresář.
- **Kontroly:** hlídá, že existují základní adresáře a že je možné načíst bezpečnostní konfiguraci.
- **Možné rozšíření:** doplnit načítání z prostředí, více režimů nasazení a detailnější validační hlášky.

---

## 3.2 Konfigurační vrstva

### Komponenta: Správa konfigurace formulářů
- **Úloha:** drží definici toho, co se má zobrazit a jaký AI scénář se má spustit.
- **Co zajišťuje:** načtení bloků formuláře, layoutu a mapování na konkrétní generátor.
- **Co je k tomu potřeba:** tři typy konfigurací:
  - definice polí,
  - rozložení formuláře,
  - propojení na AI generátor.
- **Kontroly:**
  - kontrola povinných klíčů v konfiguraci,
  - kontrola vazeb mezi bloky, layouty a generátory,
  - kontrola neplatných odkazů na neexistující části.
- **Přínos:** nové formuláře je možné zavádět hlavně konfiguračně.
- **Možné rozšíření:**
  - administrace konfigurace přes interní rozhraní,
  - verzování konfigurací,
  - schvalovací workflow,
  - přesun konfigurací do databáze.

### Aktuálně definovaný generátor
- **Název scénáře:** pokročilé generování e-mailových předmětů.
- **Používané vstupy:**
  - jazyk,
  - tón komunikace,
  - cíl sdělení,
  - typ kampaně,
  - použití emoji,
  - personalizace,
  - zdroj obsahu,
  - vlastní text nebo šablona.
- **Výstup:** více návrhů předmětů nebo jejich částí.

---

## 3.3 Formulářová vrstva

### Komponenta: Generátor formulářů
- **Úloha:** skládá formulář automaticky z definovaných bloků.
- **Co zajišťuje:** vytváří jednotlivá pole a skládá je do výsledného formuláře.
- **Co je k tomu potřeba:** správně nadefinované bloky a layout.
- **Kontroly:**
  - zda existuje vhodný typ pole,
  - zda pole obsahuje potřebná metadata,
  - zda lze zvolený blok vykreslit.
- **Možné rozšíření:** přidat složitější prvky, vícekrokové formuláře nebo podmíněné scénáře.

### Podporované typy polí
- textové pole,
- číselné pole,
- datum,
- skryté pole,
- výběrové pole,
- textová oblast,
- radio volby,
- checkbox,
- přepínač typu toggle,
- souborová příloha.

### Co formulářová vrstva umožňuje z pohledu produktu
- vytvořit formulář bez ručního HTML,
- seskupovat pole do sekcí,
- řídit viditelnost polí podle jiných voleb,
- předvyplňovat hodnoty z nadřazené aplikace,
- připravit formulář pro více typů AI scénářů.

### Co se zde kontroluje
- povinná pole,
- minimální délka vstupu,
- readonly/disabled režim,
- výchozí hodnoty,
- podmíněná viditelnost,
- správné mapování vstupů na AI klíče.

### Možná vylepšení
- datumový picker,
- WYSIWYG editor,
- nahrávání více souborů,
- kroky formuláře typu wizard,
- adaptivní logika podle role uživatele,
- vlastní validace podle obchodních pravidel.

---

## 3.4 Layout a vykreslení formuláře

### Komponenta: Layout manager a renderer
- **Úloha:** určuje, kde bude které pole na obrazovce.
- **Co zajišťuje:** rozdělení do sekcí, sloupců a bloků.
- **Co je k tomu potřeba:** popis sekcí a rozložení v konfiguraci.
- **Kontroly:**
  - validace rozložení,
  - kontrola neplatných odkazů na bloky,
  - kontrola technických parametrů layoutu.
- **Přínos:** formulář lze upravovat bez zásahů do šablon.
- **Možné rozšíření:** mobilní layouty, záložky, akordeony, vícesloupcové varianty podle zařízení.

---

## 3.5 Šablonovací vrstva

### Komponenta: Vlastní template engine
- **Úloha:** skládá finální HTML výstup formuláře a výsledků.
- **Co zajišťuje:** render formulářů, jednotlivých polí, výsledků a akcí nad výsledky.
- **Co je k tomu potřeba:** šablony pro atomy, elementy a výsledkové komponenty.
- **Kontroly:**
  - práce s cache,
  - základní bezpečnost při kompilaci šablon,
  - obnova šablon při změně.
- **Přínos:** vysoká kontrola nad HTML bez závislosti na externím frameworku.
- **Možné rozšíření:** přepis šablon na úrovni hostitelské aplikace, více layoutových variant, tematizace.

### Hlavní šablonové oblasti
- obal formuláře,
- popisky a doplňkové texty,
- jednotlivé formulářové elementy,
- zobrazení výsledků,
- akční tlačítka u výsledků,
- ikony a vizuální prvky.

---

## 3.6 AI integrační vrstva

### Komponenta: AI klient a zpracování odpovědí
- **Úloha:** komunikuje s externím AI API.
- **Co zajišťuje:** odeslání vstupů, převod odpovědi do interní struktury a následné vykreslení.
- **Co je k tomu potřeba:** API URL, API klíč, timeouty a mapování vstupů.
- **Kontroly:**
  - kontrola spojení,
  - timeouty,
  - kontrola prázdné odpovědi,
  - kontrola JSON formátu,
  - základní chybové větvení.
- **Přínos:** oddělená AI vrstva umožňuje později měnit poskytovatele nebo scénáře.
- **Možné rozšíření:**
  - více AI providerů,
  - měření spotřeby,
  - logy požadavků,
  - fronta úloh,
  - asynchronní režim,
  - cachování výsledků.

### Aktuální AI scénář
- generování e-mailových předmětů,
- možnost generovat více návrhů najednou,
- možnost znovu vygenerovat jen jeden konkrétní klíč/výsledek.

---

## 3.7 API handler a endpointy

### Komponenta: API handler
- **Úloha:** backendový přijímací bod pro požadavky z formuláře.
- **Co zajišťuje:** ověření bezpečnosti, výběr správného AI scénáře, sestavení požadavku a vrácení HTML výsledku.
- **Co je k tomu potřeba:** podepsané parametry, správná konfigurace endpointu a vstupní data z formuláře.
- **Kontroly:**
  - kontrola přítomnosti podepsaných dat,
  - kontrola bezpečnostního podpisu,
  - kontrola existence endpointu,
  - kontrola formátu JSON,
  - kontrola chyb AI provideru.
- **Přínos:** jednotné chování pro všechny budoucí AI moduly.
- **Možné rozšíření:** middleware pipeline, dávkové zpracování, streaming na serveru, audit a monitoring.

### Aktuálně připravený endpoint
- `CreateSubject` pro generování e-mailových předmětů.

### Co lze do budoucna přidat
- generování preheaderů,
- generování těla e-mailu,
- generování variant CTA,
- generování produktových popisů,
- generování textů pro sociální sítě,
- interní chatbot odpovědi,
- doporučení lokalizace textů.

---

## 3.8 Bezpečnostní vrstva

### Komponenta: Podepisování parametrů
- **Úloha:** chrání technická data před manipulací mezi frontendem a backendem.
- **Co zajišťuje:** podepisování přenášených parametrů a jejich časové omezení.
- **Co je k tomu potřeba:** tajný klíč a bezpečnostní konfigurace.
- **Kontroly:**
  - délka tajného klíče,
  - platnost podpisu,
  - expirace podpisu,
  - ochrana proti opakovanému použití starých dat.
- **Přínos:** menší riziko zneužití interních parametrů nebo endpointů.
- **Možné rozšíření:** CSRF ochrana, rate limiting, IP omezení, auditní záznamy.

---

## 3.9 Asset a provozní vrstva

### Komponenta: Správa JS/CSS assetů
- **Úloha:** zajišťuje vložení stylů a skriptů do stránky.
- **Co zajišťuje:** aby se assety nenačetly vícekrát při více formulářích na jedné stránce.
- **Co je k tomu potřeba:** build výstupy frontendu.
- **Kontroly:** základní kontrola jednorázového vložení.
- **Možné rozšíření:** verzování assetů, CDN, optimalizace načítání.

### Komponenta: Error handler
- **Úloha:** zachytává chyby aplikace.
- **Co zajišťuje:** zobrazení technických detailů pro vývoj nebo obecné informace pro provoz.
- **Kontroly:** výjimky, PHP chyby, fatální chyby.
- **Možné rozšíření:** propojení na monitoring, e-mail notifikace, centralizované logování.

---

## 3.10 Překladová vrstva

### Komponenta: Translator
- **Úloha:** základ pro vícejazyčné rozhraní.
- **Co zajišťuje:** načítání překladů ze souborů a fallback hodnot.
- **Současný stav:** připravený základ, ale není plně zapojen do hlavního toku aplikace.
- **Možné rozšíření:** plné vícejazyčné UI, správa překladů z administrace, případně databázové překlady.

---

## 4. Frontend — soupis komponent a jejich funkce

## 4.1 Hlavní frontendová aplikace

### Komponenta: App
- **Úloha:** startuje celé chování na stránce.
- **Co zajišťuje:** inicializaci formuláře, služeb, validace a navázání akcí.
- **Co je k tomu potřeba:** DOM formuláře, tlačítko pro odeslání, výsledkový blok.
- **Kontroly:** ověří validitu formuláře ještě před odesláním.
- **Možné rozšíření:** více formulářů na stránce, více režimů provozu, lazy inicializace.

---

## 4.2 Komunikační a servisní vrstva

### Komponenta: EventBus
- **Úloha:** interní komunikační páteř frontendu.
- **Co zajišťuje:** předávání událostí mezi částmi aplikace bez pevné vazby.
- **Přínos:** jednotlivé moduly jsou oddělené a lépe rozšiřitelné.
- **Možné rozšíření:** debug panel, sledování toku událostí, širší integrační API pro hostitelskou aplikaci.

### Komponenta: ApiClient
- **Úloha:** sjednocuje odesílání požadavků.
- **Co zajišťuje:** výběr komunikačního kanálu, middleware logiku a fallback režimy.
- **Co je k tomu potřeba:** alespoň jeden dostupný transport.
- **Kontroly:** sleduje start a konec požadavku, vyhodnocuje úspěch a fallback.
- **Možné rozšíření:** pokročilé řízení priorit, metriky výkonu, frontování požadavků.

### Komponenta: SessionManager
- **Úloha:** drží poslední úspěšně odeslaná data formuláře.
- **Co zajišťuje:** znovugenerování jednotlivé části bez ztráty kontextu.
- **Přínos:** rychlejší práce s výsledky a úspora AI volání.
- **Možné rozšíření:** perzistence do session/local storage, historie předchozích běhů.

---

## 4.3 Přenosové kanály

### HTTP transport
- **Úloha:** standardní způsob komunikace se serverem.
- **Co zajišťuje:** klasické odeslání formuláře a přijetí JSON odpovědi.
- **Kontroly:** timeout, validace odpovědi, chybové hlášky.
- **Vhodné pro:** běžné rychlé požadavky.

### SSE transport
- **Úloha:** podpora průběžného streamování výsledků.
- **Co zajišťuje:** postupné doručování průběhu a výsledků.
- **Současný stav:** frontend je na to dobře připraven, backend má základ, ale aktuální provoz vrací primárně klasickou odpověď.
- **Přínos:** lepší uživatelský dojem u delších AI operací.
- **Možné rozšíření:** plné serverové streamování výsledků v reálném čase.

### WebSocket transport
- **Úloha:** příprava na budoucí real-time obousměrnou komunikaci.
- **Současný stav:** připravený základ pro další etapu.
- **Vhodné pro budoucnost:** chat, dlouhé úlohy, interaktivní asistenty.

---

## 4.4 Middleware vrstva frontendu

### Retry middleware
- **Úloha:** opakuje neúspěšné požadavky.
- **Přínos:** vyšší odolnost při dočasném výpadku.
- **Možné rozšíření:** inteligentní pravidla podle typu chyby.

### Cache middleware
- **Úloha:** krátkodobě ukládá stejné požadavky.
- **Přínos:** zrychlení opakovaných akcí a menší zátěž.
- **Možné rozšíření:** jemnější pravidla expirace a audit cache hitů.

### Timing middleware
- **Úloha:** základ pro měření výkonu.
- **Přínos:** vhodné pro budoucí monitoring a SLA.

---

## 4.5 Funkční frontendové moduly

### Komponenta: Validace formuláře
- **Úloha:** kontrola před odesláním.
- **Co zajišťuje:**
  - kontrolu povinných polí,
  - kontrolu prázdných hodnot,
  - zobrazení chyb přímo u pole,
  - fokus na první chybné pole.
- **Přínos:** menší počet neplatných požadavků na backend.
- **Možné rozšíření:** jazykové hlášky, obchodní validace, asynchronní validace.

### Komponenta: Visibility controller
- **Úloha:** podmíněné zobrazení polí.
- **Co zajišťuje:** reaguje na volbu uživatele a podle ní skryje nebo ukáže navázaná pole.
- **Přínos:** přehlednější formulář a menší chybovost při vyplňování.
- **Kontroly:** při skrytí pole zároveň zabrání jeho odeslání.
- **Možné rozšíření:** složitější logické podmínky a závislosti mezi více poli.

### Komponenta: ResultActionHandler
- **Úloha:** obsluhuje akce nad výsledky.
- **Aktuálně pokryté akce:**
  - znovu generovat konkrétní položku,
  - kopírovat výsledek.
- **Připravené, ale zatím nedokončené akce:**
  - označení palcem nahoru,
  - označení palcem dolů,
  - použít výsledek do nadřazené aplikace.
- **Přínos:** lepší práce s jednotlivými návrhy bez opakování celého procesu.
- **Možné rozšíření:** uložení oblíbených výsledků, feedback scoring, přímé vložení do CMS.

---

## 4.6 UI komponenty

### CustomSelect
- **Úloha:** zlepšuje vzhled a ovládání výběrových polí.
- **Co zajišťuje:** vlastní stylování, klávesovou navigaci a synchronizaci s původním selectem.
- **Přínos:** profesionálnější UX bez externí knihovny.

### LayoutController
- **Úloha:** aplikuje rozložení formuláře na stránce.
- **Co zajišťuje:** převod konfiguračních parametrů na reálné zobrazení v gridu.
- **Přínos:** stejná konfigurace se projeví konzistentně i na frontendu.

### ProgressLoader
- **Úloha:** připravená komponenta pro průběh dlouhého generování.
- **Co zajišťuje:** zobrazení fáze, průběhu, počtu výsledků a času.
- **Současný stav:** dobrý základ pro streamovaný režim.
- **Možné rozšíření:** detailní stavové zprávy, procenta, odhad času dokončení.

---

## 5. Uživatelský tok aplikace

### Standardní scénář použití
1. Zobrazit formulář podle zvoleného generátoru.
2. Předvyplnit technické nebo kontextové hodnoty z hostitelské aplikace.
3. Uživatel doplní obchodní vstupy.
4. Frontend provede základní kontrolu formuláře.
5. Backend ověří bezpečnostní data.
6. Systém připraví AI požadavek.
7. AI vrátí výsledky.
8. Výsledky se vykreslí do uživatelského rozhraní.
9. Uživatel může:
   - zkopírovat návrh,
   - znovu vygenerovat jednotlivou položku,
   - v budoucnu ohodnotit nebo použít výsledek dál.

---

## 6. Kontroly a validace v systému

## 6.1 Kontroly na úrovni konfigurace
- správná struktura JSON souborů,
- existence povinných atributů,
- validní odkazy mezi generátory, layouty a bloky,
- kontrola formátu layoutových parametrů.

## 6.2 Kontroly na úrovni formuláře
- povinná pole,
- minimální délka textu,
- správné zpracování radio/checkbox skupin,
- vypnutí skrytých polí, aby se neposílala,
- předvyplnění hodnot jen do správných bloků.

## 6.3 Kontroly na úrovni bezpečnosti
- kontrola přítomnosti podepsaných dat,
- ověření HMAC podpisu,
- kontrola expirace parametrů,
- minimální požadovaná délka bezpečnostního klíče.

## 6.4 Kontroly na úrovni komunikace s AI
- kontrola dostupnosti API,
- timeouty,
- kontrola formátu odpovědi,
- práce s prázdnou odpovědí,
- zpracování chybových stavů provideru.

## 6.5 Kontroly na úrovni provozu
- zachycení runtime chyb,
- základní ochrana template vrstvy,
- cache šablon,
- fallback transporty ve frontendu.

---

## 7. Závislosti projektu

## 7.1 Backendové závislosti
- PHP 8.1 a vyšší,
- rozšíření `curl`,
- rozšíření `json`,
- Composer pro autoload a distribuci balíčku.

### Důležité zjištění
- Backend nemá velký framework typu Laravel nebo Symfony.
- To znamená menší technologickou zátěž, ale zároveň více vlastního kódu k dlouhodobé údržbě.

## 7.2 Frontendové závislosti
- TypeScript,
- esbuild,
- esbuild-sass-plugin,
- vlastní SCSS a vlastní TypeScript moduly.

### Důležité zjištění
- Projekt nemá runtime závislost na React, Vue ani jQuery.
- To je výhodné z hlediska lehkosti a výkonu.
- Nevýhodou je potřeba více vlastního vývoje při rozšiřování UI.

## 7.3 Externí systémová závislost
- externí AI API služby VirtualZoom.

### Co to znamená pro provoz
- kvalita a dostupnost výsledků závisí i na dostupnosti externí AI vrstvy,
- vhodné je doplnit monitoring, SLA a fallback scénáře.

---

## 8. Datová vrstva a návrh databáze

## 8.1 Současný stav
- Projekt aktuálně není postavený na aktivním databázovém úložišti.
- Pracuje hlavně s konfigurací v JSON souborech, se souborovou cache a s krátkodobou frontendovou relací.
- To je vhodné pro rychlý start a menší počet scénářů.

## 8.2 Kdy bude databáze vhodná
- při růstu počtu generátorů,
- při potřebě historie generování,
- při sběru zpětné vazby,
- při správě oprávnění,
- při schvalování šablon a konfigurací,
- při auditu a reportingu.

## 8.3 Doporučené databázové oblasti

### A. Generátory a konfigurace
- **Účel:** ukládat formuláře a AI scénáře mimo JSON soubory.
- **Co ukládat:**
  - seznam generátorů,
  - verze konfigurací,
  - sekce a bloky,
  - aktivní/neaktivní stav,
  - datum publikace.
- **Přínos:** snadnější správa a možnost interní administrace.

### B. Historie generování
- **Účel:** uchovat, kdo co generoval a s jakým výsledkem.
- **Co ukládat:**
  - uživatel,
  - typ generátoru,
  - vstupní parametry,
  - vrácené výsledky,
  - čas vytvoření,
  - stav požadavku,
  - doba zpracování.
- **Přínos:** reporting, audit, opětovné použití, optimalizace promptů.

### C. Uživatelská zpětná vazba
- **Účel:** vyhodnocovat kvalitu AI výstupů.
- **Co ukládat:**
  - like/dislike,
  - označení „použito“,
  - volitelný komentář,
  - vazba na konkrétní výsledek.
- **Přínos:** zlepšování promptů a měření obchodní hodnoty.

### D. Šablony a obsahové zdroje
- **Účel:** provázat AI s interními zdroji dat.
- **Co ukládat:**
  - textové šablony,
  - produktové podklady,
  - kampaně,
  - jazykové varianty,
  - metadata pro AI generování.

### E. Audit a bezpečnostní logy
- **Účel:** dohledatelnost provozu.
- **Co ukládat:**
  - bezpečnostní incidenty,
  - neplatné podpisy,
  - chybové odpovědi API,
  - provozní výpadky,
  - změny konfigurace.

## 8.4 Minimální návrh tabulek do budoucna
- `ai_generators`
- `ai_generator_versions`
- `ai_blocks`
- `ai_layouts`
- `ai_runs`
- `ai_run_results`
- `ai_feedback`
- `ai_templates`
- `ai_audit_log`
- `ai_translations`

## 8.5 Doporučení pro databázový rozvoj
- začít historií generování a feedbackem,
- následně přesunout konfiguraci z JSON do administrace,
- později doplnit reporting a schvalovací workflow.

---

## 9. Silné stránky řešení

- modulární architektura,
- relativně lehký provoz bez těžkého frameworku,
- rychlé přidávání nových formulářů,
- oddělení konfigurace od logiky,
- připravenost na více komunikačních režimů,
- bezpečnostní podepisování parametrů,
- možnost regenerace jednotlivých částí výsledku,
- nízká frontendová závislost na externích knihovnách.

---

## 10. Rizika a slabší místa

- silná závislost na dostupnosti externí AI služby,
- aktuálně chybí databázová historie a auditní vrstva,
- chybí plně dotažený feedback loop z uživatelských akcí,
- překladová vrstva je připravená, ale ne plně nasazená,
- streamovací režim je připravený hlavně na frontendu, ale není dotažený jako hlavní provozní cesta,
- vlastní template engine a vlastní frontend utility znamenají vyšší odpovědnost za údržbu.

---

## 11. Doporučené směry dalšího rozvoje

## 11.1 Krátkodobě
- dokončit ukládání a vyhodnocení feedbacku,
- doplnit historii generování,
- zprovoznit plné použití akce „Použít“ do nadřazené aplikace,
- zlepšit provozní logování a monitoring,
- rozšířit portfolio AI generátorů.

## 11.2 Střednědobě
- zavést databázi pro historii a konfigurace,
- vytvořit interní správu generátorů,
- doplnit role a oprávnění,
- aktivovat překladový systém,
- doplnit reporting využití.

## 11.3 Dlouhodobě
- více AI providerů,
- plné streamování výsledků,
- real-time režim přes WebSocket,
- A/B testování promptů a výstupů,
- automatické doporučování vstupů,
- napojení na CRM, CMS nebo e-mailing platformu.

---

## 12. Doporučený produktový backlog v bodech

### Formuláře a UX
- vytvořit další AI formuláře pro nové use case scénáře,
- vytvořit vícekrokové formuláře,
- vytvořit responzivní varianty rozhraní,
- vytvořit lepší práci s validací a nápovědou.

### Výsledky a práce s obsahem
- vytvořit ukládání výsledků,
- vytvořit označení oblíbených návrhů,
- vytvořit možnost vložit výsledek do cílové aplikace,
- vytvořit hodnocení kvality výsledků.

### Řízení a reporting
- vytvořit historii generování,
- vytvořit provozní dashboard,
- vytvořit přehled výkonu AI scénářů,
- vytvořit audit změn konfigurací.

### Správa a administrace
- vytvořit správu generátorů v administraci,
- vytvořit správu šablon a promptů,
- vytvořit správu jazykových mutací,
- vytvořit role a oprávnění.

### Technický rozvoj s byznysovým dopadem
- vytvořit databázovou vrstvu pro produkční škálování,
- vytvořit monitoring externí AI služby,
- vytvořit fallback scénáře při výpadku provideru,
- vytvořit metriky spotřeby a úspěšnosti.

---

## 13. Shrnutí pro project managera

- Projekt je kvalitní základ pro interní AI platformu, ne jen pro jeden formulář.
- Architektura je připravená na rozšiřování do dalších AI scénářů.
- Největší současná hodnota je rychlé nasazení nových formulářových generátorů bez velkého vývoje.
- Největší prostor pro další růst je v databázi, historii, feedbacku, administraci a reportingu.
- Z pohledu roadmapy jde o velmi vhodný základ pro postupný přerod z knihovny na interní AI produktovou platformu.
