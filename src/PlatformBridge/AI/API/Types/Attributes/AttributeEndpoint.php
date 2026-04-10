<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Types\Attributes;

use PlatformBridge\AI\API\Enum\ResponseType;
use PlatformBridge\AI\API\Core\Endpoint\EndpointDefinition;

/**
 * Bázová třída pro endpointy definované pomocí PHP atributu #[Endpoint].
 *
 * Veškerá metadata (název, generátor, typ odpovědi, šablona, variantKey)
 * se automaticky načítají z atributu — není potřeba implementovat abstract metody.
 *
 * Pro transformaci vstupních dat stačí přepsat metodu {@see transformInput()}.
 * Alternativně lze runtime transformaci injektovat přes {@see setTransform()}.
 *
 * Minimální endpoint (bez transformace):
 *
 *   #[Endpoint(name: 'SimpleGen', generator: 'simple', responseType: 'string')]
 *   class SimpleEndpoint extends AttributeEndpoint {}
 *
 * Endpoint s vlastní transformací:
 *
 *   #[Endpoint(name: 'CreateSubject', generator: 'subject', responseType: 'nested',
 *              template: '/Components/NestedResult', variantKey: 'type')]
 *   class CreateSubjectEndpoint extends AttributeEndpoint
 *   {
 *       protected function transformInput(array $input, mixed ...$context): array
 *       {
 *           $variant = $context[0] ?? null;
 *           if ($variant === 'template') {
 *               unset($input['topic_source']);
 *           }
 *           return $input;
 *       }
 *   }
 *
 * @see Endpoint           Atribut s definicí metadata endpointu
 * @see EndpointDefinition Abstraktní rodičovská třída s veškerou logikou
 */
abstract class AttributeEndpoint extends EndpointDefinition
{
    /** Metadata načtená z #[Endpoint] atributu */
    private Endpoint $meta;

    /** Volitelná callable transformace (injektovaná zvenčí, má nejvyšší prioritu) */
    private ?\Closure $externalTransform = null;

    public function __construct()
    {
        $this->meta = self::readAttribute(static::class);
        $this->variantKey = $this->meta->variantKey;
    }

    // ── Metadata z atributu ─────────────────────────────────────

    protected function getEndpoint(): string
    {
        return $this->meta->name;
    }

    protected function getGeneratorId(): ?string
    {
        return $this->meta->generator;
    }

    protected function getResponseType(): ResponseType
    {
        return $this->meta->responseType;
    }

    protected function getTemplate(): string
    {
        return $this->meta->template;
    }

    // ── Transform ───────────────────────────────────────────────

    /**
     * Injektuje externí transform callable (z bridge-config.php).
     *
     * Pokud je nastavena, má NEJVYŠŠÍ prioritu — přepisuje {@see transformInput()}.
     * To umožňuje měnit chování endpointu z konfigurace bez úpravy třídy.
     *
     * @param callable $transform Funkce s signaturou: fn(array $input, mixed ...$context): array
     * @return self
     */
    public function setTransform(callable $transform): self
    {
        $this->externalTransform = $transform(...);
        return $this;
    }

    /**
     * Transformuje vstupní data.
     *
     * Pořadí priority:
     *   1. Externí callable ({@see setTransform()}) — má nejvyšší prioritu
     *   2. Override v potomcích — vlastní logika transformace
     *   3. Výchozí chování — passthrough (vrací $input beze změny)
     *
     * Přepiš v potomcích pro vlastní logiku:
     *
     *   protected function transformInput(array $input, mixed ...$context): array
     *   {
     *       $variant = $context[0] ?? null;
     *       if ($variant === 'template') {
     *           unset($input['topic_source']);
     *       }
     *       return $input;
     *   }
     *
     * @param array $input    Vstupní data z formuláře
     * @param mixed ...$context Kontextové parametry (typicky název varianty)
     * @return array Transformovaná data
     */
    protected function transformInput(array $input, mixed ...$context): array
    {
        // 1. Externí callable (z bridge-config) má nejvyšší prioritu
        if ($this->externalTransform !== null) {
            return ($this->externalTransform)($input, ...$context);
        }

        // 2. Výchozí: passthrough — přepiš v potomcích pro vlastní logiku
        return $input;
    }

    // ── Statické utility ────────────────────────────────────────

    /**
     * Načte #[Endpoint] atribut z dané třídy.
     *
     * @param class-string $className FQCN třídy s atributem
     * @return Endpoint Instance atributu s metadata
     *
     * @throws \LogicException Pokud třída nemá #[Endpoint] atribut
     */
    private static function readAttribute(string $className): Endpoint
    {
        $ref = new \ReflectionClass($className);
        $attrs = $ref->getAttributes(Endpoint::class);

        if (empty($attrs)) {
            throw new \LogicException(
                "Třída '{$className}' musí mít atribut #[Endpoint(...)]. "
                . "Přidejte atribut s povinným parametrem 'name' a volitelnými parametry "
                . "(generator, responseType, template, variantKey)."
            );
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Přečte název endpointu z #[Endpoint] atributu BEZ vytváření instance třídy.
     *
     * Používá se v {@see EndpointRegistry} pro registraci endpointů
     * z konfigurace, kde klíč (název) není explicitně uveden.
     *
     * @param class-string $className FQCN třídy s atributem
     * @return string Název endpointu z atributu
     *
     * @throws \LogicException Pokud třída nemá #[Endpoint] atribut
     */
    public static function resolveEndpointName(string $className): string
    {
        return self::readAttribute($className)->name;
    }
}
