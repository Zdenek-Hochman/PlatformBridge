<?php

declare(strict_types=1);

namespace PlatformBridge\Handler;

use PlatformBridge\Handler\Fields\FieldHandler;

/**
 * Registry handlerů s lazy-load podporou.
 *
 * Handlery nejsou instanciovány při registraci, ale až při prvním použití.
 * Routing je založen na #[HandlerAttribute] — component + variant lookup.
 */
final class HandlerRegistry
{
    /** @var array<string, \Closure(): FieldHandler> klíč → factory */
    private array $factories = [];

    /** @var array<string, FieldHandler> klíč → cached instance */
    private array $instances = [];

    /** @var (\Closure(): FieldHandler)|null */
    private ?\Closure $defaultFactory = null;

    private ?FieldHandler $defaultInstance = null;

    /**
     * Registruje handler třídu pomocí jejího #[HandlerAttribute].
     * Handler bude vytvořen lazy — až při prvním resolve().
     *
     * @param class-string<FieldHandler> $handlerClass
     * @throws \RuntimeException Pokud třída nemá #[HandlerAttribute]
     */
    public function register(string $handlerClass): void
    {
        $attr = self::readAttribute($handlerClass);
        $component = $attr->component->value;
        $factory = static fn(): FieldHandler => new $handlerClass();

        if (empty($attr->variants)) {
            $this->factories[$component] = $factory;
            return;
        }

        foreach ($attr->variants as $variant) {
            $this->factories["{$component}:{$variant}"] = $factory;
        }
    }

    /**
     * Registruje custom factory pod explicitním klíčem.
     *
     * Umožňuje přidat vlastní handler bez nutnosti atributu.
     * Klíč může být "component:variant" nebo libovolný identifikátor.
     *
     * @example $registry->addFactory('input:color', fn() => new CustomColorPickerHandler());
     */
    public function addFactory(string $key, \Closure $factory): void
    {
        $this->factories[$key] = $factory;
        unset($this->instances[$key]);
    }

    /**
     * Nastaví výchozí handler (lazy).
     *
     * @param class-string<FieldHandler> $handlerClass
     */
    public function setDefaultHandler(string $handlerClass): void
    {
        $this->defaultFactory = static fn(): FieldHandler => new $handlerClass();
        $this->defaultInstance = null;
    }

    /**
     * Resolve handler pro daný konfigurační blok.
     *
     * Pořadí resolving:
     *   1. component:variant (přesný match)
     *   2. component (celý typ — select, textarea, tick-box)
     *   3. variant (custom factory klíče)
     *   4. default handler
     *
     * @param array $block Konfigurační blok s klíči 'component' a 'variant'
     * @return FieldHandler|null
     */
    public function resolve(array $block): ?FieldHandler
    {
        $component = $block['component'] ?? null;
        $variant = $block['variant'] ?? null;

        // 1. Přesný match component:variant
        if ($component !== null && $variant !== null) {
            $key = "{$component}:{$variant}";
            if (isset($this->factories[$key])) {
                return $this->getInstance($key);
            }
        }

        // 2. Match pouze na component (select, textarea, tick-box)
        if ($component !== null && isset($this->factories[$component])) {
            return $this->getInstance($component);
        }

        // 3. Match podle variant (custom factory klíče)
        if ($variant !== null && isset($this->factories[$variant])) {
            return $this->getInstance($variant);
        }

        // 4. Default handler
        return $this->getDefaultInstance();
    }

    /**
     * Vrátí (a cachuje) instanci handleru pro daný klíč.
     */
    private function getInstance(string $key): FieldHandler
    {
        return $this->instances[$key] ??= ($this->factories[$key])();
    }

    /**
     * Vrátí (a cachuje) výchozí handler.
     */
    private function getDefaultInstance(): ?FieldHandler
    {
        if ($this->defaultFactory === null) {
            return null;
        }

        return $this->defaultInstance ??= ($this->defaultFactory)();
    }

    /**
     * Přečte #[HandlerAttribute] z třídy handleru.
     *
     * @param class-string $handlerClass
     * @throws \RuntimeException Pokud třída nemá #[HandlerAttribute]
     */
    private static function readAttribute(string $handlerClass): HandlerAttribute
    {
        $ref = new \ReflectionClass($handlerClass);
        $attrs = $ref->getAttributes(HandlerAttribute::class);

        if (empty($attrs)) {
            throw new \RuntimeException(
                "Handler {$handlerClass} musí mít atribut #[HandlerAttribute]."
            );
        }

        return $attrs[0]->newInstance();
    }
}
