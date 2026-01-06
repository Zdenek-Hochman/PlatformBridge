<?php

declare(strict_types=1);

namespace Parser;

/**
 * Parsuje URL parametry pro AI generátor
 * 
 * Formát URL:
 *   ?generator=subject&get[WebId]=1157&body[ClientId]=633
 * 
 * - generator: povinný parametr
 * - get[...]: parametry pro GET část AI endpointu
 * - body[...]: parametry pro BODY curlu
 * 
 * Bezpečnost je zajištěna whitelistem povolených hodnot v konfiguraci.
 */
class UrlParameterParser
{
	private const REQUIRED_PARAM = 'generator';

	/** @var array<string, string> */
	private array $getParams = [];

	/** @var array<string, string> */
	private array $bodyParams = [];

	private string $generator;

	/**
	 * @param array<string, mixed> $queryParams
	 */
	public function __construct(array $queryParams)
	{
		$this->parseParams($queryParams);
	}

	/**
	 * Vytvoří instanci z $_GET
	 */
	public static function fromGlobals(): self
	{
		return new self($_GET);
	}

	/**
	 * Rozparsuje parametry
	 */
	private function parseParams(array $queryParams): void
	{
		// Validace povinného parametru
		if (!isset($queryParams[self::REQUIRED_PARAM]) || $queryParams[self::REQUIRED_PARAM] === '') {
			throw new \InvalidArgumentException(
				sprintf('Povinný parametr "%s" chybí nebo je prázdný.', self::REQUIRED_PARAM)
			);
		}

		$this->generator = (string) $queryParams[self::REQUIRED_PARAM];

		// GET params pro AI endpoint
		if (isset($queryParams['get']) && is_array($queryParams['get'])) {
			$this->getParams = $this->normalizeParamArray($queryParams['get']);
		}

		// BODY params pro curl payload
		if (isset($queryParams['body']) && is_array($queryParams['body'])) {
			$this->bodyParams = $this->normalizeParamArray($queryParams['body']);
		}
	}

	/**
	 * Normalizuje pole parametrů (klíče → snake_case, filtruje prázdné)
	 *
	 * @param array<string, mixed> $params
	 * @return array<string, string>
	 */
	private function normalizeParamArray(array $params): array
	{
		$result = [];

		foreach ($params as $key => $value) {
			if ($value === '' || $value === null) {
				continue;
			}

			$normalizedKey = $this->normalizeKey((string) $key);
			$result[$normalizedKey] = (string) $value;
		}

		return $result;
	}

	/**
	 * Normalizuje klíč na snake_case
	 * WebId -> web_id, ClientId -> client_id
	 */
	private function normalizeKey(string $key): string
	{
		// PascalCase/camelCase -> snake_case
		$result = preg_replace('/([a-z])([A-Z])/', '$1_$2', $key);
		return strtolower($result);
	}

	/**
	 * Vrátí ID generátoru (povinný parametr)
	 */
	public function getGenerator(): string
	{
		return $this->generator;
	}

	/**
	 * Parametry pro GET část AI endpointu
	 *
	 * @return array<string, string>
	 */
	public function getQueryParams(): array
	{
		return $this->getParams;
	}

	/**
	 * Parametry pro BODY (curl payload)
	 *
	 * @return array<string, string>
	 */
	public function getBodyParams(): array
	{
		return $this->bodyParams;
	}

	/**
	 * Vrátí všechny parametry pro hidden inputy
	 * Odděluje GET a BODY prefixy pro pozdější zpracování v API
	 *
	 * @return array<string, string>
	 */
	public function getAllForHiddenInputs(): array
	{
		$result = [];

		foreach ($this->getParams as $key => $value) {
			$result["get.{$key}"] = $value;
		}

		foreach ($this->bodyParams as $key => $value) {
			$result["body.{$key}"] = $value;
		}

		return $result;
	}

	public function hasQueryParam(string $key): bool
	{
		return isset($this->getParams[$this->normalizeKey($key)]);
	}

	public function hasBodyParam(string $key): bool
	{
		return isset($this->bodyParams[$this->normalizeKey($key)]);
	}

	public function getQueryParam(string $key): ?string
	{
		return $this->getParams[$this->normalizeKey($key)] ?? null;
	}

	public function getBodyParam(string $key): ?string
	{
		return $this->bodyParams[$this->normalizeKey($key)] ?? null;
	}
}
