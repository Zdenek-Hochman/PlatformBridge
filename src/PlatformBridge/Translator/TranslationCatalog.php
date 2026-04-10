<?php

declare(strict_types=1);

namespace PlatformBridge\Translator;

/**
 * Drží načtené překlady pro aktuální locale.
 *
 * Ukládá překlady organizované podle domén (errors, ui, api, config).
 * Poskytuje metody pro získání překladů a celých katalogů.
 */
final class TranslationCatalog
{
    /**
     * Překlady: doména → [klíč => přeložený text]
     * @var array<string, array<string, string>>
     */
    private array $translations = [];

    /**
     * @param string $locale Kód locale (např. 'cs', 'en')
     */
    public function __construct(
        private readonly string $locale,
    ) {}

    /**
     * Přidá překlady pro danou doménu.
     * Pokud doména už existuje, překlady se SLOUČÍ (pozdější přepíšou dřívější).
     *
     * @param Domain $domain Překladová doména
     * @param array<string, string> $messages Klíč → přeložený text
     */
    public function add(Domain $domain, array $messages): void
    {
        $existing = $this->translations[$domain->value] ?? [];
        $this->translations[$domain->value] = array_merge($existing, $messages);
    }

    /**
     * Vrátí překlad pro daný klíč a doménu.
     *
     * @param Domain $domain Překladová doména
     * @param string $key Klíč překladu v tečkové notaci (např. 'http.400')
     * @return string|null Přeložený text nebo null pokud neexistuje
     */
    public function get(Domain $domain, string $key): ?string
    {
        return $this->translations[$domain->value][$key] ?? null;
    }

    // /**
    //  * Vrátí zda překlad existuje.
    //  */
    // public function has(Domain $domain, string $key): bool
    // {
    //     return isset($this->translations[$domain->value][$key]);
    // }

    // /**
    //  * Vrátí všechny překlady pro danou doménu.
    //  *
    //  * @param Domain $domain Překladová doména
    //  * @return array<string, string> Klíč → text
    //  */
    // public function allForDomain(Domain $domain): array
    // {
    //     return $this->translations[$domain->value] ?? [];
    // }

    // /**
    //  * Vrátí kompletní překladovou mapu (všechny domény).
    //  *
    //  * @return array<string, array<string, string>> doména → [klíč → text]
    //  */
    // public function all(): array
    // {
    //     return $this->translations;
    // }

    // /**
    //  * Vrátí aktuální locale.
    //  */
    // public function getLocale(): string
    // {
    //     return $this->locale;
    // }

    // /**
    //  * Vrátí počet všech překladů napříč doménami.
    //  */
    // public function count(): int
    // {
    //     $count = 0;
    //     foreach ($this->translations as $messages) {
    //         $count += count($messages);
    //     }
    //     return $count;
    // }
}
