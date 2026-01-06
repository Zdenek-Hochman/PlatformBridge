<?php

namespace Handler;

/**
 * Tovární třída pro vytváření formulářových prvků na základě bloků konfigurace.
 */
class FieldFactory
{
    /**
     * Konstruktor přijímá registr handlerů, který slouží k vyhledání správného handleru podle bloku.
     *
     * @param HandlerRegistry $registry Registr handlerů
     */
    public function __construct(private HandlerRegistry $registry) {}

    /**
     * Vytvoří pole prvků na základě zadaného bloku konfigurace.
     *
     * @param array $block Konfigurační blok popisující prvek
     * @return array Vytvořené prvky
     * @throws \RuntimeException Pokud není nalezen vhodný handler
     */
    public function createFromBlock(array $block): array
    {
        // Najde vhodný handler podle typu bloku
        $handler = $this->registry->resolve($block);

        // Pokud handler neexistuje, vyhodí výjimku
        if (!$handler) {
            throw new \RuntimeException(
                "No handler for block type: ".json_encode($block)
            );
        }

        // Vytvoří prvek pomocí nalezeného handleru
        return $handler->create($block);
    }
}