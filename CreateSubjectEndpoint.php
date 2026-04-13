<?php

use PlatformBridge\AI\API\Types\Attributes\{
    AttributeEndpoint,
    Endpoint
};
use PlatformBridge\AI\API\Enum\ResponseType;

#[Endpoint(
    name: 'CreateSubject',
    generator: 'subject',
    responseType: ResponseType::Nested,
    template: '/Components/NestedResult',
    variantKey: 'type'
)]
class CreateSubjectEndpoint extends AttributeEndpoint
{
	private const EXCLUDED_KEYS = [
		'template' => ['email_topic', 'topic_source'],
		'custom' => ['template_id', 'topic_source'],
	];

	private function pipeline(string $variant): array
	{
		return [
			fn(array $input) => $this->removeExcludedKeys($input, $variant),
			// fn(array $input) => $this->normalizeValues($input),
		];
	}

	protected function transformInput(array $input, mixed ...$context): array
	{
		[$variant] = $context;

		return array_reduce(
			$this->pipeline($variant),
			fn(array $carry, callable $fn) => $fn($carry),
			$input
		);
	}

	private function removeExcludedKeys(array $input, string $variant): array
	{
		$excludedKeys = self::EXCLUDED_KEYS[$variant] ?? [];

		return array_diff_key($input, array_flip($excludedKeys));
	}
}
