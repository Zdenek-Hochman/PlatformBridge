<?php

use Zoom\PlatformBridge\AI\API\EndpointDefinition;

class CreateSubjectEndpoint extends EndpointDefinition
{
    protected ?string $variantKey = 'type';

    protected function getEndpoint(): string {
        return 'CreateSubject';
    }

    protected function getResponseType(): string {
        return self::RESPONSE_NESTED;
    }

    protected function getTemplate(): string {
        return '/Components/NestedResult';
    }

    protected function getGeneratorId(): ?string {
        return 'subject';
    }

    protected function transformInput(array $input, mixed ...$context): array {
        $variant = $context[0] ?? null;
        if ($variant === 'template') {
            unset($input['topic_source']);
        }
        return $input;
    }
}