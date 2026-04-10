<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Core\Response;

use PlatformBridge\Paths\PathResolver;
use PlatformBridge\AI\API\Core\Endpoint\EndpointDefinition;
use PlatformBridge\AI\Client\{AiResponse, AiResponseRenderer};

/**
 * Sestavuje finální odpověď API.
 *
 * Výstupní struktura:
 * - api:      meta status (success, status_code, meta)
 * - provider: detaily endpointu (endpoint, response_type, single_key)
 * - data:     raw + parsed + HTML
 */
final class ApiResponseBuilder
{
    public function __construct(
        private readonly PathResolver $paths,
    ) {}

    public function build(
        AiResponse $response,
        EndpointDefinition $endpoint,
        array $input,
        array $params,
    ): array {
        $parsed = $endpoint->parseResponse($response->getResponse());

        $html = AiResponseRenderer::create($this->paths, [])->render(
            $parsed,
            $endpoint->getActiveTemplate(),
            [
                'variant'       => $endpoint->detectVariant($input),
                'response_type' => $endpoint->getActiveResponseType()->value,
                'single_key'    => $endpoint->getSingleKey(),
            ],
        );

        $data = $response->toArray();

        return [
            'api' => [
                'success'     => $data['success'],
                'status_code' => $data['status_code'],
                'meta'        => $data['meta'],
            ],
            'provider' => [
                'success'     => true,
                'status_code' => 200,
                'meta'        => [
                    'endpoint'      => $params['config']['endpoint'] ?? 'unknown',
                    'response_type' => $endpoint->getActiveResponseType(),
                    'single_key'    => $endpoint->getSingleKey(),
                ],
            ],
            'data' => [
                'raw'    => $data['response'],
                'parsed' => $parsed,
                'html'   => $html,
            ],
        ];
    }
}
