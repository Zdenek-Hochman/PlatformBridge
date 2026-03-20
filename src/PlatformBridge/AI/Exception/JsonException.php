<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\AI\Exception;

use Throwable;

class JsonException extends \JsonException
{
    public const ERROR_DEPTH = 3001;
    public const ERROR_SYNTAX = 3002;
    public const ERROR_UTF8 = 3003;

    private const ERROR_CODE_MAP = [
        JSON_ERROR_DEPTH => self::ERROR_DEPTH,
        JSON_ERROR_SYNTAX => self::ERROR_SYNTAX,
        JSON_ERROR_UTF8 => self::ERROR_UTF8,
    ];

    protected array $context = [];

	public function __construct(string $message, int $code = 0, ?Throwable $previous = null, array $context = []) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

	public function getContext(): array
    {
        return $this->context;
    }

	public static function invalidJson(string $message, \JsonException $exception, array $context = []): self
	{
		return new self(
			"{$message}",
			self::ERROR_CODE_MAP[$exception->getCode()] ?? self::ERROR_SYNTAX,
			null,
			array_merge(['status_code' => $exception->getCode()], $context)
		);
	}
}