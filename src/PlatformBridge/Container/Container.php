<?php

declare(strict_types=1);

namespace PlatformBridge\Container;

/**
 * Jednoduchý DI kontejner s lazy-loading a singleton podporou.
 *
 * Služby jsou registrovány jako tovární funkce (closures) a vytvářeny
 * teprve při prvním požadavku. Každá služba je vytvořena pouze jednou (singleton).
 *
 * @package PlatformBridge\Container
 */
final class Container
{
    /** @var array<string, \Closure> Registrované tovární funkce */
    private array $factories = [];

    /** @var array<string, object> Cache vytvořených instancí (singletony) */
    private array $instances = [];

    /**
     * Registruje tovární funkci pro daný identifikátor služby.
     *
     * @param string $id Unikátní identifikátor služby (typicky FQCN)
     * @param \Closure(self): object $factory Tovární funkce přijímající kontejner
     * @return self
     */
    public function set(string $id, \Closure $factory): self
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]); // invalidace cache při přeregistraci

        return $this;
    }

    /**
     * Vrátí instanci služby. Při prvním volání ji vytvoří, poté vrací cache.
     *
     * @template T of object
     * @param class-string<T> $id Identifikátor služby
     * @return T
     *
     * @throws ContainerException Pokud služba není registrována
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw ContainerException::serviceNotFound($id);
        }

        $this->instances[$id] = ($this->factories[$id])($this);

        return $this->instances[$id];
    }

    /**
     * Zjistí, zda je služba registrována.
     *
     * @param string $id Identifikátor služby
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }

    /**
     * Vymaže konkrétní instanci z cache (vynutí nové vytvoření při dalším get()).
     *
     * @param string $id Identifikátor služby
     * @return self
     */
    public function reset(string $id): self
    {
        unset($this->instances[$id]);

        return $this;
    }
}
