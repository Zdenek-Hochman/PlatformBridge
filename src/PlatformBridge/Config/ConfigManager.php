<?php

declare(strict_types=1);

namespace PlatformBridge\Config;

use PlatformBridge\Shared\Exception\ConfigException;

/**
 * Centrální přístupový bod pro konfiguraci PlatformBridge.
 *
 * Odpovědnosti:
 * - Koordinace mezi ConfigLoader, ConfigResolver a ConfigValidator
 * - Lazy load konfigurace
 * - Poskytování API pouze pro čtení
 *
 * Deleguje:
 * - Načítání souborů → ConfigLoader
 * - Validaci → ConfigValidator
 * - Resolvování referencí → ConfigResolver
 */
final class ConfigManager
{
    private bool $loaded = false;

    /** @var array<string, array> */
    private array $blocks = [];

    /** @var array<string, array> */
    private array $layouts = [];

    /** @var array<string, array> */
    private array $generators = [];

    private ConfigResolver $resolver;

    /**
     * @param ConfigLoader $loader Loader s fallback mechanikou
     */
    public function __construct(private readonly ConfigLoader $loader)
    {
        $this->resolver = new ConfigResolver();
    }

    /**
     * Načte konfiguraci ze souborů a předá data resolveru.
     *
     * Metoda provádí lazy loading konfigurace. Pokud již byla konfigurace načtena,
     * metoda se ukončí. Jinak načte data pomocí loaderu, uloží je do příslušných
     * vlastností a předá je resolveru pro další zpracování. Nakonec nastaví příznak
     * $loaded na true, aby zabránila opakovanému načítání.
     */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $data = $this->loader->load();

        $this->blocks = $data['blocks'];
        $this->layouts = $data['layouts'];
        $this->generators = $data['generators'];

        $this->resolver->setData($this->blocks, $this->layouts, $this->generators);

        $this->loaded = true;
    }

    private function ensureLoaded(): void
    {
        if (!$this->loaded) {
            $this->load();
        }
    }

    /**
     * Vrátí rozřešený generátor podle zadaného ID.
     *
     * Metoda zajistí, že konfigurace je načtena (lazy loading),
     * a následně vrátí rozřešený generátor pomocí resolveru.
     * Pokud generátor neexistuje, vyvolá výjimku ConfigException.
     *
     * @param string $generatorId ID generátoru
     * @return array Rozřešený generátor
     * @throws ConfigException Pokud generátor neexistuje
     */
    public function getGenerator(string $generatorId): array
    {
        $this->ensureLoaded();
        return $this->resolver->resolveGenerator($generatorId);
    }

    /**
     * Vyhledá a vrátí rozřešený generátor podle zadaného ID.
     *
     * Metoda zajistí, že konfigurace je načtena (lazy loading),
     * a následně vrátí rozřešený generátor pomocí resolveru.
     * Pokud generátor neexistuje, vrací null místo vyvolání výjimky.
     *
     * @param string $generatorId ID generátoru
     * @return array|null Rozřešený generátor nebo null, pokud neexistuje
     */
    public function findGenerator(string $generatorId): ?array
    {
        $this->ensureLoaded();
        return $this->resolver->findResolvedGenerator($generatorId);
    }

    /**
     * Vrátí konfigurační data generátoru podle zadaného ID.
     *
     * Metoda vyhledá rozřešený generátor a vrátí jeho konfigurační sekci,
     * pokud existuje. Pokud generátor nebo jeho konfigurace neexistuje,
     * vrací null.
     *
     * @param string $generatorId ID generátoru
     * @return array|null Konfigurace generátoru nebo null, pokud neexistuje
     */
    public function getGeneratorConfig(string $generatorId): ?array
    {
        $generator = $this->findGenerator($generatorId);
        return $generator[ConfigKeys::CONFIG->value] ?? null;
    }

    /**
     * Vrátí hodnotu z konfigurace generátoru podle zadané cesty.
     *
     * Metoda prochází konfigurační pole generátoru podle cesty oddělené tečkami
     * (např. "ai.model") a vrací nalezenou hodnotu. Pokud cesta neexistuje nebo
     * konfigurace není pole, vrací výchozí hodnotu.
     *
     * @param string $generatorId ID generátoru
     * @param string $path Cesta v konfiguraci oddělená tečkami (např. "ai.model")
     * @param mixed $default Výchozí hodnota, pokud cesta neexistuje
     * @return mixed Hodnota z konfigurace nebo výchozí hodnota
     */
    public function getConfigValue(string $generatorId, string $path, mixed $default = null): mixed
    {
        $config = $this->getGeneratorConfig($generatorId);

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
     * Vrátí sekce rozřešeného layoutu podle zadaného ID.
     *
     * Metoda zajistí, že konfigurace je načtena (lazy loading),
     * a následně vrátí pole sekcí rozřešeného layoutu pomocí resolveru.
     *
     * @param string $layoutId ID layoutu
     * @return array Pole sekcí rozřešeného layoutu
     */
    public function getResolvedSections(string $layoutId): array
    {
        $this->ensureLoaded();
        return $this->resolver->resolveLayoutSections($layoutId);
    }

    /**
     * Vrátí bloky v sekci layoutu podle zadaného ID sekce.
     *
     * Metoda vyhledá sekci v rozřešeném layoutu podle ID a vrátí pole bloků,
     * které jsou v této sekci. Pokud sekce neexistuje, vrací prázdné pole.
     *
     * @param string $layoutId ID layoutu
     * @param string $sectionId ID sekce v layoutu
     * @return array Pole bloků v dané sekci
     */
    public function getSectionBlocks(string $layoutId, string $sectionId): array
    {
        $sections = $this->getResolvedSections($layoutId);

        foreach ($sections as $section) {
            if (($section[ConfigKeys::ID->value] ?? null) === $sectionId) {
                return $section[ConfigKeys::BLOCKS->value] ?? [];
            }
        }

        return [];
    }
}
