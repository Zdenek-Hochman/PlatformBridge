<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\Form\Element;

/**
 * Abstraktní základní třída pro různé typy polí formuláře.
 * Obsahuje společné pomocné metody, které mohou podtřídy používat.
 */
abstract class Element
{
	/**
     * Pole pro specifické argumenty konkrétního typu pole.
     * Může být naplněno v podtřídách.
     *
     * @var array
     */
    protected array $specificArguments = [];

	/**
	 * Pole pro uložení datových atributů pole.
	 *
	 * @var array $meta Asociativní pole datových atributů.
	 */
	protected array $meta = [];

    public function __construct(protected array $data)
    {
        $this->meta = $data['meta'] ?? [];
    }

    /**
     * Připraví strukturu pole formuláře na základě vstupních dat.
     *
     * Podtřídy by měly vrátit asociativní pole nebo objekt se strukturou, kterou
     * používají pro další zpracování/renderování.
     *
     * @return object Asociativní pole se strukturou pole formuláře.
     */
    abstract public function prepareFormElementStructure(): object;

    /**
     * Vygeneruje HTML (resp. string) pro vykreslení pole formuláře.
     *
     * Podtřída by zde měla vytvořit potřebné HTML podle dat a případné šablony.
     *
     * @param array $data Asociační pole s daty potřebnými pro vykreslení (label, value, attributes...).
     * @param object $template Objekt/šablona, kterou lze použít při renderování (volitelné, podle implementace).
     * @return string HTML string reprezentující pole.
     */
    abstract public function renderFormElement(array $data, object $template): string;

	/**
	 * Připraví pole datových atributů pro HTML výstup.
	 *
	 * Pro každý zadaný atribut vytvoří dvojici:
	 *   - klíč: 'data-nazev' (kebab-case)
	 *   - hodnota: původní hodnota (string)
	 *
	 * @param array $dataAttributes Asociativní pole datových atributů ['název' => 'hodnota'].
	 * @return array Asociativní pole připravených atributů ['data-nazev' => 'hodnota'].
	 */
	protected static function prepareDataAttributes(array $meta): array
	{
		$prepared = [];

		foreach ($meta as $key => $value) {
			// Pokud klíč již začíná na data- nebo aria-, použijeme ho přímo
			if (str_starts_with($key, 'data-') || str_starts_with($key, 'aria-')) {
				$prepared[$key] = $value;
			} else {
				$attrName = 'data-' . self::normalizeDataKey($key);
				$prepared[$attrName] = $value;
			}
		}

		return $prepared;
	}

	/**
	 * Normalizuje klíč pro data-* atributy z camelCase na kebab-case.
	 *
	 * Například: aiKey -> ai-key
	 *
	 * @param string $key Klíč v camelCase.
	 * @return string Klíč převedený na kebab-case (malá písmena, spojovníky).
	 */
    private static function normalizeDataKey(string $key): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $key));
    }

    /**
 	 * Naformátuje hodnotu atributu name pro input.
	 *
	 * Tato metoda vezme řetězec a nahradí v něm všechny tečky (.) znakem #.
	 *
	 * @param string $text Název, který má být přeformátován.
     * @return string Upravený řetězec.
     */
    protected function formatNameAttribute(string $text): string
    {
        return str_replace('.', '#', $text);
    }

	/**
	 * Odfiltruje prázdné atributy z pole.
	 *
	 * Ponechá hodnoty 0, false, "0" - odstraní pouze "" a null.
	 *
	 * @param array $attributes Asociativní pole atributů.
	 * @return array Pole bez prázdných hodnot.
	 */
	protected function filterEmptyAttributes(array $attributes): array
	{
		return array_filter($attributes, fn($value) => $value !== "" && $value !== null);
	}

    /**
     * Očistí vstupní řetězec.
     *
     * Provede trim, odstraní escapovací lomítka (stripslashes) a převede speciální
     * HTML znaky na entity (htmlspecialchars).
     *
     * @param string $data Vstupní text.
     * @return string Sanitizovaný text.
     */
    public function sanitize(string $data): string
    {
		return htmlspecialchars(trim($data), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Připraví běžný atribut (např. 'required', 'disabled' apod.) podle dat.
     *
     * Vrátí samotný název atributu pokud je v $data nastaven na true, jinak prázdný řetězec.
     *
     * @param string $attribute Název atributu (např. 'required').
     * @param array $data Pole, kde se kontroluje existence a pravdivost atributu.
     * @return string Buď název atributu nebo prázdný řetězec.
     */
    protected function prepareAttribute(string $attribute, array $data): string
    {
        return isset($data[$attribute]) && $data[$attribute] == true ? $attribute : '';
    }

	/**
     * Připraví hodnotu atributu autocomplete.
     *
     * Vrací string typu "autocomplete=on" (nebo jiná hodnota) nebo prázdný string.
     *
     * @return string
     */
	protected function prepareAutocomplete(array $common): string
	{
		if (!isset($common['autocomplete']) || $common['autocomplete'] === '') {
			return '';
		}

		$value = $this->sanitize((string)$common['autocomplete']);

		return "autocomplete={$value}";
	}

	protected function preparePlaceholder(array $common): string
	{
		if (!isset($common['placeholder']) || $common['placeholder'] === '') {
			return '';
		}

		$value = $this->sanitize((string)$common['placeholder']);
		return "placeholder=\"{$value}\"";
	}

	/**
     * Přiřadí společné (commonAttributes) hodnoty do templating engine.
     *
     * @param array $commonData
     * @param object $engine
     * @return void
     */
    protected function assignCommonData(array $commonData, object $engine): void
    {
   		array_walk($commonData, function ($value, $key) use ($engine) {
            if (is_string($value)) {
                // Pokud hodnota již obsahuje kompletní HTML atribut (např. placeholder="..."),
                // nesanitizujeme ji znovu, aby nedošlo k dvojitému escapování
                if (!$this->isPreformattedAttribute($value)) {
                    $value = $this->sanitize($value);
                }
            }
            $engine->assign($key, $value);
        });
    }

    /**
     * Zjistí, zda hodnota je již předformátovaný HTML atribut.
     *
     * Předformátované atributy mají formát: nazev="hodnota" nebo nazev=hodnota
     * a již jsou sanitizované, takže je nechceme escapovat znovu.
     *
     * @param string $value Hodnota k ověření.
     * @return bool True pokud je hodnota předformátovaný atribut.
     */
    private function isPreformattedAttribute(string $value): bool
    {
        // Detekuje vzor: nazev="..." nebo nazev=hodnota (bez mezer před =)
        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*=/', $value);
    }

	/**
 	 * Převede pole extra atributů na jeden string a přiřadí ho do engine jako 'Extra'.
     *
     * - Hodnoty se obalí do uvozovek, pokud nejsou boolean true (v tom případě se jen uvede název atributu).
     * - Všechny hodnoty i klíče jsou sanitizovány.
     *
     * @param array $extraData
     * @param object $engine
     * @return void
     */
    protected function assignExtraData(array $extraData, object $engine): void
    {
        $pairs = [];

        foreach ($extraData as $key => $value) {
            $k = $this->sanitize((string)$key);

            // boolean true -> atribut bez hodnoty (např. "disabled"), prázdné/null -> přeskočit
            if ($value === true) {
                $pairs[] = $k;
                continue;
            }
            if ($value === false || $value === null || $value === '') {
                continue;
            }

            // pojistka: pokud je pole, serializujeme na JSON (nebo jinak dle potřeby)
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $v = $this->sanitize((string)$value);
            // přidáme uvozovky kolem hodnoty
            $pairs[] = "{$k}=\"{$v}\"";
        }

        $extra = implode(' ', $pairs);

        $engine->assign('elementSpecificAttributes', $extra);
    }

	/**
     * Univerzální skládací metoda – dynamicky volá obalovače/appendery podle pořadí v poli.
     * Každá metoda musí mít signaturu (string $html, array $data, object $engine): string
     *
     * @param array $data
     * @param object $engine
     * @param string $template
     * @param array $sequence Pole názvů sekcí/metod v požadovaném pořadí (např. ['wrapWithLabel', 'renderSmall'])
     * @return string
     */
    protected function renderComposedElement(array $data, object $engine, string $template, array $sequence): string
    {
        $html = $engine->render($template);

        foreach ($sequence as $section) {
            // Název metody musí být v této třídě definován
            if (method_exists($this, $section)) {
                // Předáváme vždy $html, odpovídající data a engine
                $sectionData = $this->getSectionData($section, $data);
                $html = $this->$section($sectionData, $engine, $html);
            }
        }
        return $html;
    }

    /**
     * Vrátí data pro danou sekci podle názvu metody (lze rozšířit pro další sekce).
     */
    protected function getSectionData(string $section, array $data): array
    {
        return match ($section) {
            'wrapWithLabel' => $data['label'] ?? [],
            'renderSmall' => $data['small'] ?? [],
            default => [],
        };
    }

	/**
	 * Obalí zadané HTML pole (input/select apod.) šablonou labelu, pokud jsou k dispozici data pro label.
	 *
	 * @param array $data Data pro label (např. text, atributy apod.).
	 * @param object $engine Instance šablonovacího engine.
	 * @param string $Element Již vyrenderované HTML pole, které má být obaleno labelem.
	 * @return string Výsledný HTML kód s obaleným polem, nebo původní pole pokud není label zadán.
	 */
	protected function wrapWithLabel(array $data, object $engine, string $element): string
	{
        if (empty($data)) return $element;

		// Klonujeme engine, abychom měli izolovaný kontext pro label.
		$labelEngine = clone $engine;

		// Do klonovaného engine přiřadíme data pro label.
		$labelEngine->assign('label', $data);

		// Do stejného engine přiřadíme vykreslené pole (input/select apod.),
		// které vygenerujeme původním enginem, aby byl výstup konzistentní.
		$labelEngine->assign('field', $element);

		// Vykreslíme šablonu pro label, která už obsahuje i vykreslené pole.
		return $labelEngine->render('/Atoms/Label');
	}

	/**
	 * Přidá k již vyrenderovanému poli (input/select apod.) HTML pro malý pomocný text (small), pokud je zadán.
	 *
	 * @param array $data  Data pro small (např. text, atributy apod.).
	 * @param object $engine Instance šablonovacího engine.
	 * @param string $Element  Již vyrenderované HTML pole, ke kterému se má small přidat.
	 * @return string Výsledný HTML kód s připojeným small, nebo původní pole pokud není small zadán.
	 */
    protected function renderSmall(array $data, object $engine, string $element): string
    {
        if (empty($data)) return $element;

        $engine->assign('small', $data);

		return $element . "\n" . $engine->render('/Atoms/Small');
    }
}
