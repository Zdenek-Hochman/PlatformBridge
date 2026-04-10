<?php

namespace PlatformBridge\Handler;

use PlatformBridge\Handler\Fields\FieldHandler;

/**
 * Registr handlerů pro výběr správného FieldHandleru podle bloku.
 *
 * Podporuje:
 * - explicitní mapování variant
 * - automatické vyhledání přes supports()
 * - fallback na default handler
 *
 * Varianty a default handler jsou instanciovány dynamicky.
 */
final class HandlerRegistry
{
    /** @var list<FieldHandler> */
    private array $handlers = [];

    /** @var array<string, class-string<FieldHandler>> */
    private array $variantMap = [];

    /** @var class-string<FieldHandler>|null */
    private ?string $defaultHandler = null;

    /**
     * Přidá handler do registru.
     * @param FieldHandler $handler Handler k registraci
     */
    public function addHandler(FieldHandler $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Namapuje konkrétní variantu na třídu handleru.
     *
     * @param string $variant Název varianty
     * @param class-string<FieldHandler> $handlerClass
     */
    public function mapVariant(string $variant, string $handlerClass): void
    {
        $this->variantMap[$variant] = $handlerClass;
    }

    /**
     * Nastaví výchozí třídu handleru, která se použije jako poslední možnost.
     *
     * @param class-string<FieldHandler> $handlerClass Název třídy handleru
     */
    public function setDefaultHandler(string $handlerClass): void
    {
        $this->defaultHandler = $handlerClass;
    }

    /**
     * Vrátí vhodný handler pro daný blok.
     *
     * Výběr probíhá v pořadí:
     * - explicitní variant mapping
     * - první handler podporující blok
     * - default handler
     *
     * @param array $block Konfigurační blok
     * @return FieldHandler|null
     *
     * @internal
     */
    public function resolve(array $block): ?FieldHandler
    {
        $variant = $block['variant'] ?? null;
        if ($variant !== null && isset($this->variantMap[$variant])) {
            return new $this->variantMap[$variant]();
        }

        foreach ($this->handlers as $handler) {
            if ($handler->supports($block)) {
                return $handler;
            }
        }

        if ($this->defaultHandler) {
            return new $this->defaultHandler();
        }

        return null;
    }
}
