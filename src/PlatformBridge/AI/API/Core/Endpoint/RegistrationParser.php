<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Core\Endpoint;

use PlatformBridge\AI\API\Types\Attributes\AttributeEndpoint;

/**
 * Parsuje a validuje konfiguraci endpointů z bridge-config.php.
 *
 * Podporuje 5 formátů registrace:
 *   - A: Numerický klíč + class-string (atribut, autoloaded)
 *   - B: Numerický klíč + ['class' => ..., 'file' => ...]
 *   - C: Numerický klíč + ['class' => ..., 'transform' => ...]
 *   - D: Pojmenovaný klíč + konfigurační pole → ConfigurableEndpoint
 *   - E: Pojmenovaný klíč + class-string → EndpointDefinition
 */
final class RegistrationParser
{
    /**
     * @return array[] Pole výsledků: [['name', 'type', 'className', 'config', 'transform'], ...]
     */
    public function parse(array $endpoints): array
    {
        $results = [];

        foreach ($endpoints as $name => $definition) {
            $results[] = $this->parseEntry($name, $definition);
        }

        return $results;
    }

    private function parseEntry(int|string $name, mixed $definition): array
    {
        // Formát A: numerický klíč + class-string
        if (is_int($name) && is_string($definition)) {
            return $this->parseAttributeClass($definition);
        }

        // Formát B/C: numerický klíč + pole s 'class'
        if (is_int($name) && is_array($definition) && isset($definition['class'])) {
            return $this->parseAttributeClassFromArray($definition);
        }

        if (!is_string($name)) {
            throw new \InvalidArgumentException(
                "Neplatná konfigurace endpointu: pro deklarativní konfiguraci musí být klíč string. "
                . "Pro třídy s #[Endpoint] atributem použijte numerický index."
            );
        }

        // Formát D: pojmenovaný klíč + konfigurační pole
        if (is_array($definition)) {
            return self::result($name, 'config', config: $definition);
        }

        // Formát E: pojmenovaný klíč + class-string
        if (is_string($definition)) {
            $this->assertClassExists($definition, $name);
            $this->assertExtendsEndpointDefinition($definition, $name);

            return self::result($name, 'class', className: $definition);
        }

        throw new \InvalidArgumentException(
            "Neplatná definice endpointu '{$name}': hodnota musí být pole nebo string (FQCN)."
        );
    }

    // ── Formáty A–C ─────────────────────────────────────────────

    private function parseAttributeClass(string $className): array
    {
        $this->assertClassExists($className);
        $this->assertIsAttributeEndpoint($className);

        return self::result(
            AttributeEndpoint::resolveEndpointName($className),
            'class',
            className: $className,
        );
    }

    private function parseAttributeClassFromArray(array $definition): array
    {
        $className = $definition['class'];
        $file = $definition['file'] ?? null;
        $transform = $definition['transform'] ?? null;

        if (!is_string($className)) {
            throw new \InvalidArgumentException(
                "Klíč 'class' pro endpoint musí být string (FQCN třídy)."
            );
        }

        if ($file !== null) {
            if (!is_string($file) || !is_file($file)) {
                throw new \InvalidArgumentException(
                    "Soubor '{$file}' pro endpoint '{$className}' nebyl nalezen. "
                    . "Tip: použijte __DIR__ pro relativní cestu."
                );
            }
            require_once $file;
        }

        $this->assertClassExists($className, file: $file);
        $this->assertIsAttributeEndpoint($className);

        return self::result(
            AttributeEndpoint::resolveEndpointName($className),
            'class',
            className: $className,
            transform: is_callable($transform) ? $transform : null,
        );
    }

    // ── Validace ────────────────────────────────────────────────

    private function assertClassExists(string $className, ?string $nameKey = null, ?string $file = null): void
    {
        if (class_exists($className)) {
            return;
        }

        if ($nameKey !== null) {
            throw new \InvalidArgumentException(
                "Třída '{$className}' pro '{$nameKey}' nebyla nalezena. "
                . "Zkontrolujte autoloading, nebo použijte formát s 'file'."
            );
        }

        $hint = ($file === null)
            ? ' Přidejte klíč \'file\', nebo zkontrolujte autoloading.'
            : " Soubor '{$file}' byl načten, ale třída v něm nebyla nalezena.";

        throw new \InvalidArgumentException("Třída '{$className}' nebyla nalezena.{$hint}");
    }

    private function assertIsAttributeEndpoint(string $className): void
    {
        if (!is_subclass_of($className, AttributeEndpoint::class)) {
            throw new \InvalidArgumentException(
                "Třída '{$className}' musí dědit z AttributeEndpoint a mít atribut #[Endpoint(...)]."
            );
        }
    }

    private function assertExtendsEndpointDefinition(string $className, string $nameKey): void
    {
        if (!is_subclass_of($className, EndpointDefinition::class)) {
            throw new \InvalidArgumentException(
                "Třída '{$className}' musí dědit z EndpointDefinition."
            );
        }
    }

    // ── Helpers ──────────────────────────────────────────────────

    private static function result(
        string $name,
        string $type,
        ?string $className = null,
        ?array $config = null,
        ?callable $transform = null,
    ): array {
        return [
            'name'      => $name,
            'type'      => $type,
            'className' => $className,
            'config'    => $config,
            'transform' => $transform,
        ];
    }
}
