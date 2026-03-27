<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\AI\API;

/**
 * Konfigurovatelný endpoint vytvořený z pole parametrů.
 *
 * Umožňuje definovat endpoint čistě deklarativně v bridge-config.php
 * bez nutnosti vytvářet vlastní PHP třídu s extends/use.
 *
 * Podporované klíče konfigurace:
 *   - generator_id    (string|null)  ID generátoru v generators.json
 *   - response_type   (string)       Typ odpovědi: 'string'|'array'|'nested'
 *   - template        (string)       Cesta k šabloně pro renderování
 *   - variant_key     (string|null)  Klíč ve vstupních datech pro detekci varianty
 *   - variants        (array)        Deklarativní pravidla pro transformaci vstupu dle varianty
 *   - transform       (callable)     Volitelná vlastní transformační funkce
 *
 * Příklad konfigurace v bridge-config.php:
 *
 *   'endpoints' => [
 *       'CreateSubject' => [
 *           'generator_id'  => 'subject',
 *           'response_type' => 'nested',
 *           'template'      => '/Components/NestedResult',
 *           'variant_key'   => 'type',
 *           'variants'      => [
 *               'template' => ['remove_fields' => ['topic_source']],
 *               'custom'   => ['remove_fields' => ['template_id', 'topic_source']],
 *           ],
 *       ],
 *   ],
 */
final class ConfigurableEndpoint extends EndpointDefinition
{
    /** @var string Název endpointu pro AI API */
    private string $name;

    /** @var array Konfigurační pole s parametry endpointu */
    private array $config;

    /**
     * @param string $name   Název endpointu (odpovídá klíči v konfiguraci)
     * @param array  $config Konfigurační pole s parametry
     */
    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->config = $config;
        $this->variantKey = $config['variant_key'] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    protected function getEndpoint(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    protected function getGeneratorId(): ?string
    {
        return $this->config['generator_id'] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    protected function getResponseType(): string
    {
        return $this->config['response_type'] ?? self::RESPONSE_STRING;
    }

    /**
     * {@inheritDoc}
     */
    protected function getTemplate(): string
    {
        return $this->config['template'] ?? self::SINGLE_KEY_TEMPLATE;
    }

    /**
     * Transformuje vstupní data podle konfigurace.
     *
     * Pořadí zpracování:
     *   1. Volitelná callable funkce ('transform') — má nejvyšší prioritu
     *   2. Deklarativní pravidla ('variants') — aplikují se podle detekované varianty
     *   3. Fallback — vstupní data bez změny
     *
     * Podporovaná pravidla ve 'variants':
     *   - remove_fields (string[])  Odstraní zadaná pole ze vstupu
     *   - keep_fields   (string[])  Ponechá pouze zadaná pole (whitelist)
     *   - defaults       (array)    Doplní výchozí hodnoty, pokud pole chybí
     *
     * @param array $input    Vstupní data z formuláře
     * @param mixed ...$context Kontextové parametry (typicky název varianty)
     * @return array Upravená vstupní data
     */
    protected function transformInput(array $input, mixed ...$context): array
    {
        // 1. Vlastní callable transformace
        $transform = $this->config['transform'] ?? null;

        if (is_callable($transform)) {
            return $transform($input, ...$context);
        }

        // 2. Deklarativní pravidla dle varianty
        $variant = $context[0] ?? null;
        $variants = $this->config['variants'] ?? [];

        if ($variant !== null && isset($variants[$variant])) {
            $rules = $variants[$variant];
            $input = $this->applyVariantRules($input, $rules);
        }

        return $input;
    }

    /**
     * Aplikuje deklarativní pravidla na vstupní data.
     *
     * @param array $input Vstupní data
     * @param array $rules Pravidla pro transformaci
     * @return array Upravená data
     */
    private function applyVariantRules(array $input, array $rules): array
    {
        // remove_fields: odstraní pole ze vstupu
        if (isset($rules['remove_fields']) && is_array($rules['remove_fields'])) {
            $input = array_diff_key($input, array_flip($rules['remove_fields']));
        }

        // keep_fields: ponechá pouze uvedená pole (whitelist)
        if (isset($rules['keep_fields']) && is_array($rules['keep_fields'])) {
            $input = array_intersect_key($input, array_flip($rules['keep_fields']));
        }

        // defaults: doplní výchozí hodnoty pro chybějící klíče
        if (isset($rules['defaults']) && is_array($rules['defaults'])) {
            $input = array_merge($rules['defaults'], $input);
        }

        return $input;
    }
}
