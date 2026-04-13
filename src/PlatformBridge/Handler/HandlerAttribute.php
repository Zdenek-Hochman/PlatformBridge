<?php

declare(strict_types=1);

namespace PlatformBridge\Handler;

use Attribute;

/**
 * Atribut pro deklarativní registraci field handlerů.
 *
 * Definuje, který typ komponenty a které varianty handler obsluhuje.
 * HandlerRegistry čte tento atribut při registraci a sestavuje
 * interní lookup mapu pro lazy-load resolving.
 *
 * @example
 *   #[HandlerAttribute(component: ComponentType::Input, variants: ['text', 'email', 'password'])]
 *   class TextHandler extends FieldConfigurator { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class HandlerAttribute
{
    /**
     * @param ComponentType $component Typ komponenty (input, select, textarea, tick-box)
     * @param array<string> $variants  Podporované varianty (prázdné = celý component type)
     * @param int $priority            Priorita při resolving (vyšší = dříve, default 0)
     */
    public function __construct(
        public readonly ComponentType $component,
        public readonly array $variants = [],
        public readonly int $priority = 0,
    ) {}
}
