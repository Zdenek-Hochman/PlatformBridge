<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\AI;

/**
 * Univerzální AI request pro jakýkoliv endpoint
 *
 * Validace dat se neprovádí - předpokládá se, že data jsou validována
 * na frontendu pomocí HTML5 atributů a JavaScriptu podle JSON konfigurace
 */
class AiRequest
{
	protected string $endpoint;
	protected array $data = [];
	protected array $queryParams = [];  // Parametry pro GET část URL
	protected array $bodyParams = [];   // Parametry pro BODY
	protected array $headers = [];
	protected string $method = 'POST';

	public function __construct(string $endpoint)
	{
		$this->endpoint = $endpoint;
	}

	/**
	 * Factory metoda pro vytvoření requestu
	 */
	public static function to(string $endpoint): static
	{
		return new static($endpoint);
	}

	public function getEndpoint(): string
	{
		return $this->endpoint;
	}

	/**
	 * Nastaví HTTP metodu
	 */
	public function usingMethod(string $method): static
	{
		$this->method = strtoupper($method);
		return $this;
	}

	/**
	 * Nastaví prompt data (formulářová data)
	 */
	public function withPrompt(array $prompt): static
	{
		$this->data = array_merge($this->data, $prompt);
		return $this;
	}

	/**
	 * Nastaví BODY parametry (budou součástí payloadu)
	 */
	public function withQueryParams(array $params): static
	{
		$this->queryParams = array_merge($this->queryParams, $params);
		return $this;
	}

	public function withHeader(string $name, string $value): static
	{
		$this->headers[$name] = $value;
		return $this;
	}

	public function getMethod(): string
	{
		return $this->method;
	}

	public function getHeaders(): array
	{
		return $this->headers;
	}

	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * Vrátí GET parametry pro URL
	 */
	public function getQueryParams(): array
	{
		return array_filter($this->queryParams, fn($v) => $v !== null && $v !== '');
	}

	/**
	 * Vrátí payload pro BODY - včetně bodyParams a prompt dat
	 */
	public function toPayload(): array
	{
		$payload = array_merge($this->bodyParams, $this->data);
		return array_filter($payload, fn($v) => $v !== null && $v !== '');
	}
}
