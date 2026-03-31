<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\AI\API;

use Attribute;

/**
 * Atribut pro deklarativní definici AI endpointu.
 *
 * Umísťuje se na třídu dědící z {@see AttributeEndpoint}.
 * Obsahuje veškerá metadata endpointu — není potřeba implementovat abstract metody.
 *
 * Použití:
 *
 *   #[Endpoint(
 *       name: 'CreateSubject',
 *       generator: 'subject',
 *       responseType: 'nested',
 *       template: '/Components/NestedResult',
 *       variantKey: 'type'
 *   )]
 *   class CreateSubjectEndpoint extends AttributeEndpoint
 *   {
 *       // Přepiš pouze pokud potřebuješ transformaci vstupních dat
 *       protected function transformInput(array $input, mixed ...$context): array
 *       {
 *           return $input;
 *       }
 *   }
 *
 * @see AttributeEndpoint Bázová třída pro endpoint s atributem
 * @see EndpointRegistry   Registr endpointů s podporou auto-discovery z atributu
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Endpoint
{
    /**
     * @param string      $name         Název endpointu pro AI API (např. 'CreateSubject')
     * @param string|null $generator    ID generátoru v generators.json (pro required fields mapping)
     * @param string      $responseType Typ odpovědi: 'string' | 'array' | 'nested'
     * @param string      $template     Cesta k šabloně pro renderování odpovědi
     * @param string|null $variantKey   Klíč ve vstupních datech pro detekci varianty (null = bez variant)
     */
    public function __construct(
        public readonly string  $name,
        public readonly ?string $generator = null,
        public readonly string  $responseType = EndpointDefinition::RESPONSE_STRING,
        public readonly string  $template = EndpointDefinition::SINGLE_KEY_TEMPLATE,
        public readonly ?string $variantKey = null,
    ) {}
}
