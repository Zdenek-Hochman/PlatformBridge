<?php

declare(strict_types=1);

namespace PlatformBridge\Translator;

/**
 * Rozpozná a nahrazuje překladové proměnné {$domain.key} v textech.
 *
 * Vzor: {$doména.klíč.cesta}        → překlad nebo klíč
 *        {$doména.klíč.cesta|Default} → překlad nebo "Default"
 *
 * Příklady:
 *   {$config.blocks.tone.label}            → "Tón komunikace" (cs)
 *   {$config.blocks.tone.label|Tone}       → "Tón komunikace" nebo "Tone" jako fallback
 *   {$errors.http.400|Bad request}         → "Neplatný požadavek." nebo "Bad request"
 */
final class VariableResolver
{
    /**
     * Regex pro rozpoznání proměnných.
     * Matchuje: {$config.blocks.tone.label} nebo {$config.blocks.tone.label|Fallback text}
     */
    private const PATTERN = '/\{\$([a-zA-Z0-9_.]+)(?:\|([^}]*))?\}/';

    public function __construct(
        private readonly Translator $translator,
    ) {}

    /**
     * Nahradí všechny proměnné v řetězci překlady.
     *
     * @param string $text Text s {$domain.key} proměnnými
     * @return string Text s nahrazenými překlady
     */
    public function resolve(string $text): string
    {
        return preg_replace_callback(self::PATTERN, function (array $match): string {
            $fullKey = $match[1];          // "config.blocks.tone.label"
            $fallback = $match[2] ?? null; // "Tone" nebo null

            // Rozděl na doménu a klíč
            $dotPos = strpos($fullKey, '.');
            if ($dotPos === false) {
                return $fallback ?? $fullKey;
            }

            $domain = substr($fullKey, 0, $dotPos);
            $key = substr($fullKey, $dotPos + 1);

            return $this->translator->t($domain, $key, [], $fallback);
        }, $text);
    }

    /**
     * Rekurzivně projde pole a nahradí proměnné ve všech string hodnotách.
     * Používá se pro zpracování celého bloku/generátoru z JSON.
     *
     * @param array $data Vstupní data (block, generator, atd.)
     * @return array Data s nahrazenými proměnnými
     */
    public function resolveArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->resolve($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->resolveArray($value);
            }
        }

        return $data;
    }

    /**
     * Detekuje zda řetězec obsahuje překladovou proměnnou.
     *
     * @param string $text Text ke kontrole
     * @return bool True pokud obsahuje {$...} proměnnou
     */
    public static function hasVariable(string $text): bool
    {
        return (bool) preg_match(self::PATTERN, $text);
    }
}
