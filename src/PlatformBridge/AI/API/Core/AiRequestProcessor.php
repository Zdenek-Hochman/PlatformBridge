<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Core;

use PlatformBridge\AI\API\Core\Endpoint\EndpointResolver;
use PlatformBridge\AI\API\Core\Response\ApiResponseBuilder;
use PlatformBridge\AI\Client\{AiClient, AiClientConfig};
use PlatformBridge\Security\{SignedParams, Exception\SecurityException};

/**
 * Orchestruje lifecycle AI požadavku:
 * verify signature → resolve endpoint → send request → build response.
 */
final class AiRequestProcessor
{
    public function __construct(
        private readonly array $securityConfig,
        private readonly array $config,
        private readonly EndpointResolver $resolver,
        private readonly ApiResponseBuilder $responseBuilder,
    ) {}

    public function process(array $input): array
    {
        $params   = $this->verifySignature($input);
        $endpoint = $this->resolver->resolve($params, $input);

        $request = $endpoint->createRequest(
            $input,
            $params['get'] ?? [],
            $params['body'] ?? [],
        );

        foreach ($params['headers'] ?? [] as $name => $value) {
            $request->withHeader($name, $value);
        }

        $response = $this->createClient()->send($request);

        return $this->responseBuilder->build($response, $endpoint, $input, $params);
    }

    private function createClient(): AiClient
    {
        return new AiClient(AiClientConfig::fromArray([
            'api_key'     => $this->config['api_key'] ?? '',
            'timeout'     => $this->config['timeout'] ?? 30,
            'max_retries' => $this->config['max_retries'] ?? 3,
            'base_url'    => $this->config['base_url'] ?? '',
            'debug'       => defined('DEBUG_MODE'),
        ]));
    }

    private function verifySignature(array &$input): array
    {
        if (!isset($input['__ai_signed'])) {
            throw new SecurityException(
                'Missing signed params (__ai_signed).',
                SecurityException::CODE_MISSING_TOKEN,
            );
        }

        $verified = (new SignedParams(
            $this->securityConfig['secretKey'],
            $this->securityConfig['ttl'] ?? null,
        ))->verify($input['__ai_signed']);

        unset($input['__ai_signed']);

        return $verified;
    }
}
