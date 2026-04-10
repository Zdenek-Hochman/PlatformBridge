<?php

namespace PlatformBridge\Handler\Fields;

interface FieldHandler
{
    /**
     * Vrátí true, pokud tento handler dokáže obsloužit daný block.
     */
    public function supports(array $block): bool;

    /**
     * Vytvoří Field/FieldProxy elementy z blocku.
     *
     * @return array
     */
    public function create(array $block): array;
}