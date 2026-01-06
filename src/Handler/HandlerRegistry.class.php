<?php

namespace Handler;
use Handler\Fields\FieldHandler;

/**
 * Registr handlerů pro zpracování různých typů bloků.
 *
 * Umožňuje registraci handlerů, mapování variant na konkrétní třídy handlerů
 * a nastavení výchozího handleru pro případy, kdy není nalezen vhodný handler.
 */
class HandlerRegistry
{
    /**
     * Pole registrovaných handlerů.
     * @var FieldHandler[]
     */
    private array $handlers = [];

    /**
     * Mapování variant na třídy handlerů (pro specifické případy).
     * @var array<string, string>
     */
    private array $variantMap = [];

    /**
     * Název třídy výchozího handleru.
     * @var string|null
     */
    private ?string $defaultHandler = null;

    /**
     * Přidá handler do registru.
     *
     * @param FieldHandler $handler Handler k registraci
     */
    public function addHandler(FieldHandler $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Namapuje konkrétní variantu na třídu handleru.
     * (Volitelné, pro budoucí rozšíření variant)
     *
     * @param string $variant Název varianty
     * @param string $handlerClass Název třídy handleru
     */
    public function mapVariant(string $variant, string $handlerClass): void
    {
        $this->variantMap[$variant] = $handlerClass;
    }

    /**
     * Nastaví výchozí třídu handleru, která se použije jako poslední možnost.
     *
     * @param string $handlerClass Název třídy handleru
     */
    public function setDefaultHandler(string $handlerClass): void
    {
        $this->defaultHandler = $handlerClass;
    }

    /**
     * Najde vhodný handler pro zadaný blok.
     *
     * 1) Pokud je v bloku explicitně uvedena varianta a je namapovaná, použije ji.
     * 2) Jinak projde všechny registrované handlery a použije první, který blok podporuje.
     * 3) Pokud žádný handler nevyhovuje, použije výchozí handler (pokud je nastaven).
     *
     * @param array $block Konfigurační blok
     * @return FieldHandler|null Nalezený handler nebo null
     */
    public function resolve(array $block): ?FieldHandler
    {
        // 1) Pokud je explicitně uvedena varianta a je namapovaná, použije se příslušný handler.
        $variant = $block['variant'] ?? null;
        if ($variant && isset($this->variantMap[$variant])) {
            return new $this->variantMap[$variant]();
        }

        // 2) Projde všechny registrované handlery a použije první, který blok podporuje.
        foreach ($this->handlers as $handler) {
            if ($handler->supports($block)) {
                return $handler;
            }
        }

        // 3) Pokud žádný handler nevyhovuje, použije se výchozí handler (pokud je nastaven).
        if ($this->defaultHandler) {
            return new $this->defaultHandler();
        }

        // Pokud není nalezen žádný handler, vrací null.
        return null;
    }
}