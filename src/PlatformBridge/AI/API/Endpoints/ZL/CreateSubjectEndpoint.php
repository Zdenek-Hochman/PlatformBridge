<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\AI\API\Endpoints\ZL;

use Zoom\PlatformBridge\AI\API\EndpointDefinition;

/**
 * Endpoint pro generování předmětu (subject)
 *
 * Required pole jsou načítána dynamicky z JSON konfigurace (blocks.json),
 * nikoliv hardcoded. Konfigurace je propojena přes:
 * generators.json -> layout_ref -> layouts.json -> blocks.json
 *
 * Response je vždy string (vygenerovaný předmět)
 */
class CreateSubjectEndpoint extends EndpointDefinition
{
    public function getEndpoint(): string
    {
        return 'CreateSubject';
    }

    /**
     * ID generátoru v generators.json pro načtení konfigurace
     */
    public function getGeneratorId(): string
    {
        return 'subject';
    }

    public function getResponseType(): string
    {
        return self::RESPONSE_NESTED;
    }

    public function getTemplate(): string
    {
        return '/Components/NestedResult';
    }

    /**
     * Transformuje vstup podle detekované varianty.
     * Využívá AI klíče z konfigurace pro mapování polí.
     */
    public function transformInput(array $input, string $variant): array
    {
        // Transformace na AI klíče z konfigurace
        $transformed = $this->transformToAiKeys($input);

        return match ($variant) {
            'template' => $this->transformTemplateVariant($input),
            'custom' => $this->transformCustomVariant($input),
            default => $transformed,
        };
    }

    /**
     * Template varianta - rozbalí šablonu na server straně
     */
    protected function transformTemplateVariant(array $originalInput): array
    {
		$excludedKeys = ['email_topic', 'topic_source'];
		return array_diff_key($originalInput, array_flip($excludedKeys));
    }

	protected function transformCustomVariant(array $originalInput): array
    {
		$excludedKeys = ['template_id', 'topic_source'];
		return array_diff_key($originalInput, array_flip($excludedKeys));
    }
}
