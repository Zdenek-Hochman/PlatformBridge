<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\AI\API;

use Zoom\PlatformBridge\AI\AiClient;
use Zoom\PlatformBridge\AI\AiClientConfig;
use Zoom\PlatformBridge\AI\Exception\AiException;
use Zoom\PlatformBridge\AI\Exception\JsonException;
use Zoom\PlatformBridge\AI\AiResponse;
use Zoom\PlatformBridge\AI\AiResponseRenderer;
use Zoom\PlatformBridge\Config\ConfigLoader;
use Zoom\PlatformBridge\Config\ConfigManager;
use Zoom\PlatformBridge\Config\ConfigValidator;
use Zoom\PlatformBridge\Paths\PathResolver;
use Zoom\PlatformBridge\Paths\PathResolverFactory;
use Zoom\PlatformBridge\Security\SecurityException;
use Zoom\PlatformBridge\Security\SignedParams;

/**
 * API Handler – zpracování příchozích AI požadavků.
 *
 * Podporuje standalone (localhost) i vendor (produkce) režim.
 * Konfigurace se načítá ze dvou oddělených souborů:
 *   - bridge-config.php: API připojení (base_url, api_key, timeout, max_retries)
 *   - security-config.php: HMAC podepisování (secretKey, ttl)
 */
final class ApiHandler
{
    /** @var array Konfigurace API připojení (base_url, api_key, timeout, max_retries) */
    private array $config;

    /** @var array Bezpečnostní konfigurace (secretKey, ttl) */
    private array $securityConfig;

    private function __construct(
        private readonly string $configPath,
        private readonly string $securityConfigPath,
        private PathResolver $paths,
    ) {
        $this->config = $this->loadConfig($this->configPath, 'Bridge');
        $this->securityConfig = $this->loadConfig($this->securityConfigPath, 'Security');
		$this->paths = $paths;

		// Registrace uživatelských endpointů z bridge-config.php
		$this->registerUserEndpoints();
    }

	public function getPathResolver(): PathResolver
    {
        return $this->paths;
    }

	/**
	 * Registruje uživatelské endpointy z bridge-config.php ('endpoints' klíč).
	 *
	 * Voláno automaticky při konstrukci ApiHandleru.
	 * Pokud klíč 'endpoints' chybí nebo je prázdné pole, registrace se přeskočí.
	 */
	private function registerUserEndpoints(): void
	{
		$endpoints = $this->config['endpoints'] ?? [];

		if (!empty($endpoints) && is_array($endpoints)) {
			EndpointRegistry::getInstance()->registerFromConfig($endpoints);
		}
	}

    /**
     * Inicializační metoda pro automatickou detekci cest ke konfiguračním souborům.
     *
     * @return self Instance třídy ApiHandler s načtenými cestami ke konfiguracím.
     */
    public static function bootstrap(): self
    {
        $paths = PathResolverFactory::auto(dirname(__DIR__, 4));

        if (!defined('BRIDGE_BOOTSTRAPPED')) {
            define('BRIDGE_BOOTSTRAPPED', true);
        }

        $configPath = $paths->bridgeConfigFile();
        $securityConfigPath = $paths->securityConfigFile();

        return new self($configPath, $securityConfigPath, $paths);
    }

    /**
     * Zpracuje příchozí HTTP požadavek a odešle odpověď ve formátu JSON.
     *
     * @return void Metoda nevrací žádnou hodnotu.
     */
    public function handle(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $this->processRequest();
        } catch (\Throwable $e) {
            self::sendRawError($e);
        }
    }

    /**
     * Zpracuje příchozí požadavek v několika krocích:
     *  1. Načte vstupní data z HTTP požadavku.
     *  2. Ověří podpis a validitu vstupních dat.
     *  3. Rozpozná cílový endpoint na základě parametrů.
     *  4. Zavolá poskytovatele AI s požadovanými daty.
     *  5. Odešle úspěšnou odpověď zpět klientovi.
     *
     * @return void Metoda nevrací žádnou hodnotu.
     */
    private function processRequest(): void
    {
        $input    = $this->parseInput();
        $params   = $this->verifySignature($input);
        $endpoint = $this->resolveEndpoint($params, $input);
        $response = $this->callAiProvider($endpoint, $input, $params);

        $this->sendSuccessResponse($response, $endpoint, $input, $params);
    }

    /**
     * Načte a dekóduje vstupní data z HTTP požadavku ve formátu JSON.
     *
     * @return array|null Pole s daty z požadavku, nebo null pokud je vstup prázdný.
     * @throws JsonException Pokud je JSON neplatný nebo nelze dekódovat.
     */
    private function parseInput()
    {
        try {
            return json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw JsonException::invalidJson('Nepodařilo se zpracovat odeslaná data.', $e, $e->getTrace());
        }
    }

    /**
     * Ověří podpis a validitu vstupních dat.
     *
     * @param array $input Reference na vstupní data, která obsahují podepsané parametry.
     * @return array Ověřené parametry získané z podepsaných dat.
     *
     * @throws RuntimeException Pokud chybí podepsané parametry nebo je podpis neplatný.
     */
    private function verifySignature(array &$input): array
    {
        if (!isset($input['__ai_signed'])) {
            throw new SecurityException(
                'Missing signed params (__ai_signed).',
                SecurityException::CODE_MISSING_TOKEN,
            );
        }

        $verified = (new SignedParams($this->securityConfig['secretKey'], $this->securityConfig['ttl'] ?? null))->verify($input['__ai_signed']);

        unset($input['__ai_signed']);

        return $verified;
    }

    /**
     * Rozpozná a vrátí cílový endpoint na základě parametrů a vstupních dat.
     *
     * @param array $params Parametry obsahující konfiguraci endpointu.
     * @param array $input Reference na vstupní data, která mohou obsahovat další informace o endpointu.
     *
     * @return EndpointDefinition Definice cílového endpointu.
     * @throws AiException Pokud chybí název endpointu v konfiguraci.
     */
    private function resolveEndpoint(array $params, array &$input): EndpointDefinition
    {
        $name = $params['config']['endpoint'] ?? throw AiException::invalidRequest('Chybí název endpointu v konfiguraci.');

        $configPath = $params['config']['config_path'] ?? $this->getPathResolver()->configPath();

        $loader = new ConfigLoader(
            $configPath,
            $this->getPathResolver(),
            new ConfigValidator(),
        );

        $registry = EndpointRegistry::getInstance();
        $registry->setConfigManager(new ConfigManager($loader));

        $endpoint = $registry->getOrFail($name);

        // Single-key mód
        $generateKey = $input['__generate_key'] ?? null;
        unset($input['__generate_key']);

        if (is_string($generateKey) && $generateKey !== '') {
            $endpoint->setSingleKeyMode($generateKey);
        }

        return $endpoint;
    }

    /**
     * Zavolá poskytovatele AI s požadovanými daty a vrátí odpověď.
     *
     * @param EndpointDefinition $endpoint Definice cílového endpointu.
     * @param array $input Vstupní data pro vytvoření požadavku.
     * @param array $params Parametry obsahující další informace, jako hlavičky a tělo požadavku.
     *
     * @return AiResponse Odpověď od poskytovatele AI.
     */
    private function callAiProvider(EndpointDefinition $endpoint, array $input, array $params): AiResponse
    {
        $request = $endpoint->createRequest($input, $params['get'] ?? [], $params['body'] ?? []);

        foreach ($params['headers'] ?? [] as $name => $value) {
            $request->withHeader($name, $value);
        }

        $client = new AiClient(AiClientConfig::fromArray([
            'api_key'     => $this->config['api_key'] ?? '',
            'timeout'     => $this->config['timeout'] ?? 30,
            'max_retries' => $this->config['max_retries'] ?? 3,
            'base_url'    => $this->config['base_url'] ?? '',
            'debug'       => defined('DEBUG_MODE'),
        ]));

        return $client->send($request);
    }

    /**
     * Odešle úspěšnou odpověď zpět klientovi ve formátu JSON.
     *
     * @param AiResponse $response Odpověď od poskytovatele AI.
     * @param EndpointDefinition $endpoint Definice cílového endpointu.
     * @param array $input Vstupní data použitá pro zpracování požadavku.
     * @param array $params Parametry obsahující další informace o požadavku.
     */
    private function sendSuccessResponse(AiResponse $response, EndpointDefinition $endpoint, array $input, array $params): void
    {
        $parsed = $endpoint->parseResponse($response->getResponse());

        $html = AiResponseRenderer::create($this->getPathResolver(), [])->render(
            $parsed,
            $endpoint->getActiveTemplate(),
            [
                'variant'       => $endpoint->detectVariant($input),
                'response_type' => $endpoint->getActiveResponseType(),
                'single_key'    => $endpoint->getSingleKey(),
            ],
        );

        $data = $response->toArray();

        echo json_encode([
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
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Načte konfiguraci ze zadaného souboru.
     *
     * @param string $path Cesta k souboru s konfigurací.
     * @param string $label Označení konfigurace pro chybové hlášení.
     * @return array Vrací pole s konfigurací.
     */
    private function loadConfig(string $path, string $label): array
    {
        if (!file_exists($path)) {
            self::sendRawError(
                AiException::invalidRequest("{$label} config not found: {$path}")
            );
            exit;
        }

        if (!defined('BRIDGE_BOOTSTRAPPED')) {
            define('BRIDGE_BOOTSTRAPPED', true);
        }

        $config = require $path;

        if (!is_array($config)) {
            self::sendRawError(
                AiException::invalidRequest("{$label} config must return an array.")
            );
            exit;
        }

        return $config;
    }

    /**
     * Odešle chybovou odpověď ve formátu JSON.
     *
     * @param \Throwable $e Výjimka nebo chyba, která má být odeslána.
     */
    private static function sendRawError(\Throwable $e): void
    {
        $status = self::resolveHttpStatus($e);
        http_response_code($status);

        $error = [
            'type'    => self::resolveErrorType($e),
            'code'    => $e->getCode() ?: $status,
            'message' => $e->getMessage(),
        ];

        // AiException a JsonException nesou strukturovaný kontext
        if ($e instanceof AiException || $e instanceof JsonException) {
            $error['context'] = $e->getContext();
        }

        // V debug režimu přidat trace
        if (defined('DEBUG_MODE') && \constant('DEBUG_MODE')) {
            $error['trace'] = $e->getTraceAsString();
        }

        echo json_encode([
            'api' => [
                'success'     => false,
                'status_code' => $status,
                'error'       => $error,
            ],
            'provider' => null,
            'data'     => null,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Určí HTTP status kód na základě typu výjimky.
     *
     * @param \Throwable $e Výjimka, pro kterou se určuje status kód.
     * @return int Vrací odpovídající HTTP status kód.
     */
    private static function resolveHttpStatus(\Throwable $e): int
    {
        if ($e instanceof SecurityException) {
            return 403;
        }
        if ($e instanceof \JsonException) {
            return 500;
        }

        if ($e instanceof AiException) {
            return match ($e->getCode()) {
                AiException::ERROR_VALIDATION => 422,
                AiException::ERROR_INVALID_REQUEST => 400,
                AiException::ERROR_TIMEOUT => 504,
                AiException::ERROR_CONNECTION => 502,
                default => 500,
            };
        }

        return 500;
    }

    /**
     * Určí typ chyby na základě typu výjimky.
     *
     * @param \Throwable $e Výjimka, pro kterou se určuje typ chyby.
     * @return string Vrací řetězec reprezentující typ chyby.
     */
    private static function resolveErrorType(\Throwable $e): string
    {
        return match (true) {
            $e instanceof SecurityException => 'security',
            $e instanceof \JsonException => 'invalid_json',
            $e instanceof AiException => 'ai_provider',
            default => 'internal_error',
        };
    }
}
