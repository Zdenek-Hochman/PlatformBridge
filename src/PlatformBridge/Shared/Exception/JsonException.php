<?php

declare(strict_types=1);

namespace PlatformBridge\Shared\Exception;

/**
 * Výjimka pro chyby při práci s JSON (dekódování, kódování, neplatná struktura).
 *
 * Tato výjimka je doménově nezávislá – lze ji použít v jakémkoli kontextu
 * (AI, Config, Template, ...) aniž by vznikla závislost na konkrétním modulu.
 *
 * Rozšiřuje nativní {@see \JsonException}, takže ji lze zachytit jak jako
 * `Shared\Exception\JsonException`, tak jako nativní `\JsonException`.
 */
class JsonException extends \JsonException
{
	public const CODE_DECODE_ERROR    = 5001;
	public const CODE_ENCODE_ERROR   = 5002;
	public const CODE_INVALID_TYPE   = 5003;
	public const CODE_DEPTH_EXCEEDED = 5004;
	public const CODE_SYNTAX_ERROR   = 5005;
	public const CODE_UTF8_ERROR     = 5006;

	/**
	 * Mapování nativních JSON chybových kódů na naše kódy.
	 */
	private const NATIVE_CODE_MAP = [
		JSON_ERROR_DEPTH          => self::CODE_DEPTH_EXCEEDED,
		JSON_ERROR_SYNTAX         => self::CODE_SYNTAX_ERROR,
		JSON_ERROR_UTF8           => self::CODE_UTF8_ERROR,
		JSON_ERROR_STATE_MISMATCH => self::CODE_DECODE_ERROR,
		JSON_ERROR_CTRL_CHAR      => self::CODE_DECODE_ERROR,
	];

	private ?string $filePath;

	public function __construct(
		string $message,
		int $code = 0,
		?string $filePath = null,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, $code, $previous);
		$this->filePath = $filePath;
	}

	/**
	 * Cesta k souboru, ve kterém chyba nastala (pokud je k dispozici).
	 */
	public function getFilePath(): ?string
	{
		return $this->filePath;
	}

	/**
	 * Dekódování JSON řetězce selhalo.
	 *
	 * @param string $error Chybová zpráva z json_decode
	 * @param string|null $filePath Volitelná cesta k souboru
	 * @param \JsonException|null $previous Původní nativní JsonException
	 */
	public static function decodeFailed(string $error, ?string $filePath = null, ?\JsonException $previous = null): self
	{
		$code = self::CODE_DECODE_ERROR;

		if ($previous !== null) {
			$code = self::NATIVE_CODE_MAP[$previous->getCode()] ?? self::CODE_DECODE_ERROR;
		}

		$message = 'Neplatný JSON';
		if ($filePath !== null) {
			$message .= ' v souboru ' . basename($filePath);
		}
		$message .= ": {$error}";

		return new self($message, $code, $filePath, $previous);
	}

	/**
	 * Kódování do JSON selhalo.
	 *
	 * @param string $error Chybová zpráva z json_encode
	 */
	public static function encodeFailed(string $error): self
	{
		return new self(
			"JSON kódování selhalo: {$error}",
			self::CODE_ENCODE_ERROR,
		);
	}

	/**
	 * JSON data mají neočekávaný typ (např. string místo array).
	 *
	 * @param string $expected Očekávaný typ (např. 'array')
	 * @param string $actual Skutečný typ (výsledek gettype())
	 * @param string|null $filePath Volitelná cesta k souboru
	 */
	public static function invalidType(string $expected, string $actual, ?string $filePath = null): self
	{
		$message = "JSON musí obsahovat {$expected}, ale obsahuje {$actual}";
		if ($filePath !== null) {
			$message = basename($filePath) . ': ' . $message;
		}

		return new self($message, self::CODE_INVALID_TYPE, $filePath);
	}
}
