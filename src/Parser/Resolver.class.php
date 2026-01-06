<?php

declare(strict_types=1);

namespace Parser;

/**
 * Resolver – načítá a zpracovává konfiguraci bloků, layoutů a generátorů.
 *
 * Datový model:
 * - blocks.json:   pojmenované bloky (např. "tone", "language", ...)
 * - layouts.json:  layouty složené ze sekcí a bloků, které odkazují přes "ref"
 * - generators.json: generátory, které odkazují na layout přes "layout_ref"
 *
 * $generator = Resolver::generator('subject');
 * $layout    = Resolver::layout('subject_advanced');
 * $block     = Resolver::block('tone');
 */
final class Resolver extends Validator
{
    // Výchozí složka, kde očekáváme JSON konfiguraci.
    private const DEFAULT_CONFIG_DIR = __DIR__ . '/../../config/json';

	// Název souboru s definicemi bloků (pole "blocks")
    private const BLOCKS_FILE = 'blocks.json';

	// Název souboru s definicemi layoutů (pole "layouts")
    private const LAYOUTS_FILE = 'layouts.json';

	// Název souboru s definicemi generátorů (pole "generators")
    private const GENERATORS_FILE = 'generators.json';

	/**
	 * Zda už byl config načten do paměti.
	 * Umožňuje lazy loading a zabraňuje opakovanému parsování JSONu.
	 */
    private static $loaded = false;

	/**
	 * Načtené bloky z blocks.json.
	 * Klíč = id bloku, hodnota = definice (pole).
	 * @var array<string,array>
	 */
    private static $blocks = [];

	/**
	 * Načtené layouty z layouts.json.
	 * Klíč = id layoutu, hodnota = definice (pole).
	 * @var array<string,array>
	 */
    private static $layouts = [];

	/**
	 * Načtené generátory z generators.json (tak jak jsou v souboru).
	 * Bez rozřešení layoutů a bloků.
	 * @var array<string,array>
	 */
    private static $generatorsRaw = [];

	/**
	 * Generátory se zpracovanými layouty a bloky
	 * (rozřešená reference na layout + bloky uvnitř).
	 * @var array<string,array>
	 */
    private static $generatorsResolved = [];

	/**
	 * Cache již rozřešených layoutů podle layout_ref.
	 * Urychluje vyhledávání a zabraňuje opakovanému rozřešování.
	 * @var array<string,array>
	 */
	private static $layoutsResolved = [];

    /**
 	 * Načte a připraví konfiguraci bloků, layoutů a generátorů.
     *
	 * @param string|null $dir Složka s JSON konfigurací (pokud není, použije se výchozí)
	 *
	 * @throws \RuntimeException pokud chybí soubory nebo jsou nevalidní
 	 * @throws \InvalidArgumentException pokud layout nebo blok obsahuje neplatný "ref"
     */
    public static function load(?string $dir = null): void
    {
    	// Pokud uživatel nepředal adresář, použijeme default
        $configDir = $dir ?? self::DEFAULT_CONFIG_DIR;

		// Ověří existenci složky
        self::validateConfigDir($configDir);

        $blocksJson = self::loadJsonFile($configDir . DIRECTORY_SEPARATOR . self::BLOCKS_FILE);
        $layoutsJson = self::loadJsonFile($configDir . DIRECTORY_SEPARATOR . self::LAYOUTS_FILE);
        $generatorsJson = self::loadJsonFile($configDir . DIRECTORY_SEPARATOR . self::GENERATORS_FILE);

		self::$blocks = parent::validateBlocksConfig($blocksJson);
    	self::$layouts = parent::validateLayoutsConfig($layoutsJson);
    	self::$generatorsRaw = parent::validateGeneratorsConfig($generatorsJson);

		// 🔍 cross-validace vztahů
		parent::validateRelations(self::$blocks, self::$layouts, self::$generatorsRaw);

        self::$generatorsResolved = self::buildGeneratorsConfig(
            self::$blocks,
            self::$layouts,
            self::$generatorsRaw
        );

        self::$loaded = true;
    }

	/**
	 * Znovu načte celou konfiguraci z JSON souborů.
	 *
	 * Použij, pokud se obsah konfiguračních souborů změnil
	 * během běhu aplikace (např. po úpravě JSONů bez reloadu webu).
	 *
	 * @param string|null $dir Volitelný adresář s konfigurací (jinak výchozí)
	 */
    public static function reload(?string $dir = null): void
    {
		self::$loaded = false;
		self::load($dir);
    }

	/**
	 * Vrací informaci, zda už byla konfigurace načtena do paměti.
	 */
    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

	/**
	 * Interní pomocná metoda, která zaručí, že konfigurace je načtená.
	 */
    private static function ensureLoaded(): void
    {
        if (!self::$loaded) {
            self::load();
        }
    }

	/**
	 * Vrátí všechny generátory včetně rozřešených layoutů a bloků.
	 *
	 * Klíč = ID generátoru (např. "subject")
	 * Hodnota = kompletní konfigurace generátoru včetně:
	 *   - layout_ref
	 *   - rozřešeného layoutu (sections + blocks)
	 *
	 * @return array<string,array>
	 */
	public static function generatorsRes(): array
	{
		self::ensureLoaded();
		return self::$generatorsResolved;
	}

	/**
	 * Vrátí konkrétní generátor podle jeho ID.
	 *
	 * Používá generatorsRes(), aby nemusela duplikovat logiku
	 * a vždy pracovala s již rozřešenými daty v paměti.
	 *
	 * @param string $id ID generátoru
	 * @return array|null konfigurace generátoru nebo null pokud neexistuje
	 */
	public static function generatorResById(string $id): ?array
	{
		return self::generatorsRes()[$id] ?? null;
	}

	/**
	 * Vrátí pole sekcí pro daný layout podle jeho ID (reference).
	 *
	 * Pokud layout s daným ID neexistuje, vrací prázdné pole.
	 * Jinak vrací pole sekcí definovaných v layoutu.
	 *
	 * @param string $layoutRef ID (reference) layoutu, jehož sekce chceme získat
	 * @return array Pole sekcí layoutu, nebo prázdné pole pokud layout neexistuje
	 */
	public static function sectionsRawByRef(string $layoutRef): array
	{
		// Zajistí, že konfigurace je načtená do paměti (lazy loading)
		self::ensureLoaded();

		// Pokud layout s daným ID neexistuje, vrátí prázdné pole
		if (!isset(self::$layouts[$layoutRef])) {
			return [];
		}

		// Vrátí pole sekcí z daného layoutu, nebo prázdné pole pokud nejsou definovány
		return self::$layouts[$layoutRef][Keys::KEY_SECTIONS->value] ?? [];
	}

	/**
	* Vrátí pole sekcí pro daný layout podle jeho ID (reference),
	* přičemž layout je již rozřešený (blokové reference jsou nahrazeny skutečnými bloky).
	*
	* Pokud layout neexistuje, vrací prázdné pole.
	*
	* @param string $layoutRef ID (reference) layoutu, jehož sekce chceme získat
	* @return array Pole rozřešených sekcí layoutu, nebo prázdné pole pokud layout neexistuje
	*/
	public static function sectionsResByRef(string $layoutRef): array
	{
		$layout = self::layoutResByRef($layoutRef);
		return $layout[Keys::KEY_SECTIONS->value] ?? [];
	}

	/**
	 * Vrátí všechny sekce ze všech layoutů včetně informace,
	 * ke kterému layoutu a indexu sekce patří.
	 *
	 * Každá položka výsledného pole obsahuje:
	 *   - 'layout': ID layoutu
	 *   - 'index': index sekce v layoutu
	 *   - 'section': samotná definice sekce
	 *
	 * @return array Pole všech sekcí napříč layouty s informací o původu
	*/
	public static function allSectionsRaw(): array
	{
		self::ensureLoaded();

		$result = [];

		foreach (self::$layouts as $layoutId => $layout) {
			foreach ($layout[Keys::KEY_SECTIONS->value] ?? [] as $index => $section) {
				$result[] = [
					'layout'  => $layoutId,
					'index'   => $index,
					'section' => $section,
				];
			}
		}

		return $result;
	}

	/**
	 * Vrátí pole bloků pro konkrétní sekci v daném layoutu.
	 *
	 * Pokud layout nebo sekce neexistuje, vrací prázdné pole.
	 *
	 * @param string $layoutRef ID (reference) layoutu
	 * @param int $sectionIndex Index sekce v layoutu
	 * @return array Pole bloků v dané sekci, nebo prázdné pole pokud sekce neexistuje
	*/
	public static function sectionBlocksRaw(string $layoutRef, int $sectionIndex): array
	{
		self::ensureLoaded();

		$sections = self::$layouts[$layoutRef][Keys::KEY_SECTIONS->value] ?? [];

		if (!isset($sections[$sectionIndex])) {
			return [];
		}

		return $sections[$sectionIndex][Keys::KEY_BLOCKS->value] ?? [];
	}

	/**
	 * Vrátí pole bloků pro konkrétní sekci v daném layoutu,
	 * přičemž layout je již rozřešený (blokové reference jsou nahrazeny skutečnými bloky).
	 *
	 * Pokud layout nebo sekce neexistuje, vrací prázdné pole.
	 *
	 * @param string $layoutRef ID (reference) layoutu
	 * @param int $sectionIndex Index sekce v layoutu
	 * @return array Pole rozřešených bloků v dané sekci, nebo prázdné pole pokud sekce neexistuje
	 */
	public static function sectionBlocksResByIndex(string $layoutRef, int $sectionIndex): array
	{
		$layout = self::layoutResByRef($layoutRef);

		if (!isset($layout[Keys::KEY_SECTIONS->value][$sectionIndex])) {
			return [];
		}

		return $layout[Keys::KEY_SECTIONS->value][$sectionIndex][Keys::KEY_BLOCKS->value] ?? [];
	}

	/**
	 * Vrátí index sekce v layoutu podle jejího ID.
	 *
	 * Prohledá všechny sekce rozřešeného layoutu (tj. s nahrazenými blokovými referencemi)
	 * a vrátí číselný index první sekce, která má zadané ID.
	 *
	 * Pokud sekce s daným ID neexistuje, vrací null.
	 *
	 * @param string $layoutRef ID (reference) layoutu, ve kterém hledáme sekci
	 * @param string $sectionId ID sekce, jejíž index chceme najít
	 * @return int|null Index sekce v layoutu, nebo null pokud sekce neexistuje
	 */
	public static function sectionIndexById(string $layoutRef, string $sectionId): ?int {
		$sections = self::sectionsResByRef($layoutRef);

		foreach ($sections as $index => $section) {
			if (($section[Keys::KEY_ID->value] ?? null) === $sectionId) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Vrátí pole rozřešených bloků pro konkrétní sekci v layoutu podle jejího ID.
	 *
	 * Nejprve najde index sekce podle jejího ID v rozřešeném layoutu,
	 * a poté vrátí pole bloků v této sekci (blokové reference jsou již nahrazeny skutečnými bloky).
	 *
	 * Pokud sekce s daným ID neexistuje, vrací prázdné pole.
	 *
	 * @param string $layoutRef ID (reference) layoutu
	 * @param string $sectionId ID sekce, jejíž bloky chceme získat
	 * @return array Pole rozřešených bloků v dané sekci, nebo prázdné pole pokud sekce neexistuje
	 */
	public static function sectionBlocksResById(string $layoutRef, string $sectionId): array {
		$index = self::sectionIndexById($layoutRef, $sectionId);

		if ($index === null) {
			return [];
		}

		return self::sectionBlocksResByIndex($layoutRef, $index);
	}

	/**
	 * Vrátí všechny definované bloky z konfigurace.
	 *
	 * Klíč = ID bloku (např. "tone", "language")
	 * Hodnota = konfigurace daného bloku (pole)
	 *
	 * @return array<string,array>
	 */
	public static function blocksRaw(): array
	{
		self::ensureLoaded();
		return self::$blocks;
	}

	/**
	 * Vrátí všechny layouty
	 *
	 * @return array<string,array>
	 */
	public static function layoutsRaw(): array
	{
		self::ensureLoaded();
		return self::$layouts;
	}

	/**
	 * Vrátí konkrétní blok podle jeho ID.
	 *
	 * @param string $ref ID bloku
	 * @return array|null konfigurace bloku nebo null pokud neexistuje
	 */
	public static function blockRawByRef(string $ref): ?array
	{
		return self::blocksRaw()[$ref] ?? null;
	}

	/**
	 * Vrátí původní (nerozřešený) layout.
	 *
	 * @param string $ref ID layoutu
	 * @return array|null raw definice layoutu nebo null pokud neexistuje
	 */
    public static function layoutRawByRef(string $ref): ?array
    {
        self::ensureLoaded();
        return self::$layouts[$ref] ?? null;
    }

	/**
	 * Vrátí layout s již rozřešenými bloky (ref → block definice).
	 *
	 * Pokud už byl layout jednou rozřešen, použije se cache
	 * a další volání neprovádí znovu parsing ani nahrazování referencí.
	 *
	 * @param string $ref ID layoutu
	 * @return array|null rozřešený layout nebo null pokud layout neexistuje
	 */
    public static function layoutResByRef(string $ref): ?array
    {
        self::ensureLoaded();

		// 1) zkusíme resolved cache
		if (isset(self::$layoutsResolved[$ref])) {
			return self::$layoutsResolved[$ref];
		}

		// 2) fallback – existuje layout v raw?
		if (!isset(self::$layouts[$ref])) {
			return null;
		}

		// Rozřešíme sekce a bloky (nahrazení {"ref": ...} za skutečné bloky)
   		$resolved = self::resolveLayout(self::$layouts[$ref], self::$blocks);

		// Uložíme do cache pro příště
    	self::$layoutsResolved[$ref] = $resolved;

        return $resolved;
    }

	/**
	 * Vrátí všechny rozřešené bloky napříč všemi layouty a sekcemi.
	 *
	 * Každá položka výsledného pole obsahuje:
	 *   - 'layout': ID layoutu
	 *   - 'section': index sekce v layoutu
	 *   - 'block': rozřešená definice bloku (včetně případných referencí)
	 *
	 * @return array Pole všech rozřešených bloků napříč layouty a sekcemi s informací o původu
	 */
	public static function allResolvedBlocks(): array
	{
		self::ensureLoaded();

		$result = [];

		foreach (self::$layouts as $layoutId => $_) {
			$layout = self::layoutRawByRef($layoutId);

			foreach ($layout[Keys::KEY_SECTIONS->value] ?? [] as $sectionIndex => $section) {
				foreach ($section[Keys::KEY_BLOCKS->value] ?? [] as $block) {
					$result[] = [
						'layout'  => $layoutId,
						'section' => $sectionIndex,
						'block'   => $block,
					];
				}
			}
		}

		return $result;
	}

	/**
	 * Poskládá finální seznam generátorů z raw konfigurace.
	 *
	 * Co udělá:
	 * - vezme každý generátor z generators.json
	 * - najde jeho layout podle "layout_ref"
	 * - rozřeší blokové reference uvnitř layoutu
	 * - doplní layout přímo do konfigurace generátoru
	 * - uloží rozřešené layouty do cache ($layoutsResolved)
	 *
	 * @param array $blocks seznam bloků z blocks.json
	 * @param array $layouts seznam layoutů z layouts.json
	 * @param array $generators seznam generátorů z generators.json
	 *
	 * @return array<string,array> generátory s vloženými layouty
	 */
    private static function buildGeneratorsConfig(array $blocks, array $layouts, array $generators): array
    {
        $result = [];
		self::$layoutsResolved = [];

        foreach ($generators as $key => $generator) {
            $layoutRef = $generator[Keys::KEY_LAYOUT_REF->value] ?? null;
            $resolvedLayout = null;

            if ($layoutRef !== null && isset($layouts[$layoutRef])) {
                $resolvedLayout = self::resolveLayout($layouts[$layoutRef], $blocks);
				self::$layoutsResolved[$layoutRef] = $resolvedLayout;
            }

            $result[$key] = [
                'id' => $generator[Keys::KEY_ID->value] ?? $key,
                'label' => $generator['label'] ?? null,
                'layout_ref' => $layoutRef,
                'layout' => $resolvedLayout,
				'config' => $generator['config'] ?? []
            ];
        }

        return $result;
    }

	/**
	 * Rozřeší celý layout — projde všechny jeho sekce
	 * a v každé sekci nahradí bloky, které obsahují {"ref": ...},
	 * za skutečné blokové definice z $blocks.
	 *
	 * Pokud layout neobsahuje žádné sekce, vrací se původní podoba.
	 *
	 * @param array $layout raw layout z JSONu (může obsahovat ref bloky)
	 * @param array $blocks seznam všech bloků (klíč = id)
	 * @return array layout se zpracovanými sekcemi a bloky
	 */
    private static function resolveLayout(array $layout, array $blocks): array
    {
		// Připravíme novou strukturu layoutu
        $resolved = $layout;
        $resolved[Keys::KEY_SECTIONS->value] = [];

		// Projdeme každou sekci a necháme ji rozřešit
        foreach ($layout[Keys::KEY_SECTIONS->value] as $section) {
            $resolved[Keys::KEY_SECTIONS->value][] = self::resolveSection($section, $blocks);
        }

        return $resolved;
    }

	/**
	 * Rozřeší jednu sekci layoutu – projde všechny její bloky
	 * a u každého nahradí referenci {"ref": "..."} za
	 * skutečnou definici bloku z $blocks (pokud je to potřeba).
	 *
	 * Pokud sekce neobsahuje žádné bloky, jen ji vrátí v původní podobě.
	 *
	 * @param array $section sekce z layoutu (může obsahovat bloky s ref)
	 * @param array $blocks seznam všech definovaných bloků
	 * @return array sekce se zpracovanými bloky
	 */
    private static function resolveSection(array $section, array $blocks): array
    {
		// Kopie původní sekce
        $sectionResolved = $section;
        $sectionResolved[Keys::KEY_BLOCKS->value] = [];

		// Projdeme bloky a necháme je individuálně rozřešit
        foreach ($section[Keys::KEY_BLOCKS->value] as $blockDef) {
            $sectionResolved[Keys::KEY_BLOCKS->value][] = self::resolveBlockDef($blockDef, $blocks);
        }

        return $sectionResolved;
    }

	/**
	 * Rozřeší definici jednoho bloku v sekci.
	 *
	 * Pokud blok obsahuje klíč "ref", znamená to, že neobsahuje
	 * vlastní konfiguraci, ale pouze odkazuje na blok definovaný
	 * v blocks.json → proto se přesměruje na resolveBlockRef().
	 *
	 * Pokud "ref" neexistuje, blok už obsahuje plnou definici
	 * (například inline) a může se vrátit beze změny.
	 *
	 * @param array $blockDef blok v layoutu (může obsahovat "ref")
	 * @param array $blocks všechny definované bloky podle ID
	 * @return array rozřešená definice bloku (nebo původní blok)
	 */
    private static function resolveBlockDef(array $blockDef, array $blocks): array
    {
		// Pokud má blok referenci na jiný blok, rozřešíme ji
        if (isset($blockDef[Keys::KEY_REF->value])) {
            return self::resolveBlockRef($blockDef[Keys::KEY_REF->value], $blocks);
        }

        return $blockDef;
    }

	/**
	 * Vrátí popisek (label) generátoru podle jeho ID.
	 *
	 * Nejprve zajistí načtení potřebných dat. Poté vyhledá generátor v poli
	 * $generatorsResolved podle zadaného ID. Pokud generátor existuje, vrátí jeho
	 * popisek ('label'), jinak vrátí null.
	 *
	 * @param string $generatorId ID generátoru, jehož popisek se má získat.
	 * @return string|null Popisek generátoru nebo null, pokud generátor neexistuje.
	 */
	public static function generatorLabel(string $generatorId): ?string
	{
		self::ensureLoaded();

		$generator = self::$generatorsResolved[$generatorId] ?? null;

		if ($generator === null) {
			return null;
		}

		return $generator['label'] ?? null;
	}

	/**
	 * Vrátí konfigurační pole pro zadaný identifikátor generátoru.
	 *
	 * Zajistí načtení potřebných dat a poté vrátí konfiguraci generátoru,
	 * pokud existuje. Pokud konfigurace pro daný generátor neexistuje,
	 * vrací hodnotu null.
	 *
	 * @param string $generatorId Identifikátor generátoru, pro který se má získat konfigurace.
	 * @return array|null Konfigurační pole generátoru nebo null, pokud neexistuje.
	 */
	public static function generatorConfig(string $generatorId): ?array
	{
		self::ensureLoaded();

		return self::$generatorsResolved[$generatorId]['config'] ?? null;
	}

	/**
	 * Vrátí hodnotu z konfiguračního pole generátoru podle zadané cesty.
	 *
	 * Prohledává konfigurační pole generátoru ($generatorId) podle cesty ($path) ve formátu "klic1.klic2...".
	 * Pokud cesta neexistuje nebo není konfigurace polem, vrací výchozí hodnotu ($default).
	 *
	 * @param string $generatorId  Identifikátor generátoru, jehož konfigurace se má použít.
	 * @param string $path         Cesta ke konkrétní hodnotě v konfiguraci, oddělená tečkami.
	 * @param mixed  $default      Výchozí hodnota, která se vrátí, pokud cesta neexistuje.
	 * @return mixed               Hodnota z konfigurace nebo výchozí hodnota.
	 */
	public static function generatorConfigPath(string $generatorId, string $path, mixed $default = null): mixed {
		$config = self::generatorConfig($generatorId);

		if (!is_array($config)) {
			return $default;
		}

		foreach (explode('.', $path) as $key) {
			if (!is_array($config) || !array_key_exists($key, $config)) {
				return $default;
			}

			$config = $config[$key];
		}

		return $config;
	}

	/**
	 * Vyhledá blok podle jeho ID v seznamu bloků.
	 *
	 * Tato metoda se používá vždy, když layout obsahuje blok ve formátu:
	 *   { "ref": "block_name" }
	 * a potřebujeme nahradit tuto referenci skutečnou blokovou definicí.
	 *
	 * Pokud blok s daným ID neexistuje, je to chyba konfigurace
	 * (layout odkazuje na neexistující blok) → vyhodíme výjimku.
	 *
	 * Navíc, pokud blok v blocks.json neměl pole "id",
	 * doplníme jej automaticky podle názvu reference,
	 * aby měl blok vždy jednoznačné ID.
	 *
	 * @param string $ref ID bloku
	 * @param array  $blocks všechny existující bloky
	 * @return array definice daného bloku
	 *
	 * @throws \InvalidArgumentException pokud blok v konfiguraci neexistuje
	 */
    private static function resolveBlockRef(string $ref, array $blocks): array
    {
		// Chybějící blok = chyba konfigurace
        if (!isset($blocks[$ref])) {
            throw new \InvalidArgumentException("Unknown block ref: {$ref}");
        }

		// Vezmeme definici bloku
        $block = $blocks[$ref];

		// Pokud blok nemá své ID, doplníme ho (bezpečnostní fallback)
        if (!isset($block[Keys::KEY_ID->value])) {
            $block[Keys::KEY_ID->value] = $ref;
        }

        return $block;
    }

	/**
	 * Načte JSON soubor z disku a vrátí jeho obsah jako pole.
	 *
	 * Metoda provádí několik úrovní kontroly:
	 * - ověří existenci souboru
	 * - ověří možnost soubor přečíst
	 * - provede json_decode s JSON_THROW_ON_ERROR
	 * - ověří, že root element je pole (object / array)
	 *
	 * @param string $path plná cesta k JSON souboru
	 * @return array obsah JSON jako PHP pole
	 *
	 * @throws \RuntimeException pokud soubor chybí, nejde přečíst nebo obsahuje nevalidní JSON
	 */
    private static function loadJsonFile(string $path): array
    {
		// 1) Soubor musí existovat
        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }

		// 2) Musíme být schopni přečíst obsah
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Unable to read config file: {$path}");
        }

		// 3) Bezpečné parsování JSONu s výjimkou při chybě
		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new \RuntimeException("Invalid JSON in config file: {$path} – " . $e->getMessage(), 0, $e);
		}

		// 4) JSON musí být objekt nebo pole
		if (!is_array($data)) {
			throw new \RuntimeException("JSON root must be an object or array in config file: {$path}");
		}

        return $data;
    }

	/**
	 * Ověří, že konfigurace může být načtena ze zadané složky.
	 *
	 * Metoda kontroluje pouze to, zda cesta existuje a jedná se o adresář.
	 * Nekontroluje přítomnost jednotlivých JSON souborů –
	 * ty se validují až při jejich načítání.
	 *
	 * Pokud adresář neexistuje, je to konfigurační chyba
	 * a načtení nemá pokračovat → vyhodíme RuntimeException.
	 *
	 * @param string $dir cesta ke složce s JSON konfigurací
	 *
	 * @throws \RuntimeException pokud neexistuje nebo není složkou
	 */
    private static function validateConfigDir(string $dir): void
    {
        if (!is_dir($dir)) {
            throw new \RuntimeException("Config directory not found: {$dir}");
        }
    }
}