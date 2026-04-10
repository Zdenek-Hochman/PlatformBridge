<?php

declare(strict_types=1);

namespace PlatformBridge\Shared\Utils;

final class TypeUtils
{
	/**
	 * Bezpečně zkontroluje, zda je hodnota pole typu T.
	 *
	 * @template T
	 * @param array $array Pole k ověření
	 * @param callable $typeCheck Funkce pro kontrolu typu (např. 'is_string')
	 * @return bool True pokud všechny prvky odpovídají typu T, jinak false
	 */
	public static function isArrayOfType(array $array, callable $typeCheck): bool
	{
		foreach ($array as $item) {
			if (!$typeCheck($item)) {
				return false;
			}
		}
		return true;
	}
}
instanceof MyClass

public static function hasKeysOfType(array $array, array $keyTypeMap): bool
{
    foreach ($keyTypeMap as $key => $typeCheck) {
        if (!array_key_exists($key, $array) || !$typeCheck($array[$key])) {
            return false;
        }
    }
    return true;
}

$isValid = TypeUtils::hasKeysOfType($paths, [
    'configPath' => 'is_string',
    'viewsPath' => 'is_string',
    'cachePath' => 'is_string',
    'resolver' => fn($v) => $v instanceof PathResolver,
]);