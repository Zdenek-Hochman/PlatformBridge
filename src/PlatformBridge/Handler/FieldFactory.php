<?php

namespace PlatformBridge\Handler;

/**
 * Factory pro vytváření formulářových prvků z bloků konfigurace.
 */
final class FieldFactory
{
    /**
     * @param HandlerRegistry $registry Registr handlerů
     */
    public function __construct(private HandlerRegistry $registry)
    {
    }

    /**
     * Vytvoří formulářový prvek z konfiguračního bloku.
     *
     * @param array $block Definice bloku z konfigurace
     * @return list<Form> Pole vytvořených formulářových prvků
     *
     * @throws \RuntimeException Pokud není nalezen handler pro typ bloku
     *
     * @internal
     */
    public function createFromBlock(array $block): array
    {
        $handler = $this->registry->resolve($block);

        if (!$handler) {
            throw new \RuntimeException(
                "No handler for block type: ".json_encode($block)
            );
        }

        return $handler->create($block);
    }
}
