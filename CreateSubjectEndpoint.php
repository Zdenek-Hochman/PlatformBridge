<?php

use Zoom\PlatformBridge\AI\API\AttributeEndpoint;
use Zoom\PlatformBridge\AI\API\Endpoint;

#[Endpoint(
    name: 'CreateSubject',
    generator: 'subject',
    responseType: 'nested',
    template: '/Components/NestedResult',
    variantKey: 'type'
)]
class CreateSubjectEndpoint extends AttributeEndpoint
{
    protected function transformInput(array $input, mixed ...$context): array
    {
       	[$variant] = $context;

        return match ($variant) {
            'template' => $this->transformTemplateVariant($input),
            'custom' => $this->transformCustomVariant($input),
            default => $input,
        };
    }

	private function transformTemplateVariant(array $originalInput): array
    {
		$excludedKeys = ['email_topic', 'topic_source'];
		return array_diff_key($originalInput, array_flip($excludedKeys));
    }

	private function transformCustomVariant(array $originalInput): array
    {
		$excludedKeys = ['template_id', 'topic_source'];
		return array_diff_key($originalInput, array_flip($excludedKeys));
    }
}