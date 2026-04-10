<?php

declare(strict_types=1);

namespace PlatformBridge\Paths;

use PlatformBridge\Shared\Utils\JsonUtils;

/**
 * Načítá konfigurační soubor platformbridge.json(.php) a vytváří {@see PathsConfig}.
 */
final class PathsLoader
{
    private static function normalizePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    /**
     * Načte konfiguraci z projectRoot. Pokud soubor neexistuje, vrátí výchozí hodnoty.
     */
    public static function load(string $projectRoot): PathsConfig
    {
        $protectedFile = self::normalizePath($projectRoot) . DIRECTORY_SEPARATOR . PathsConfig::CONFIG_FILE_PROTECTED;

        if (!file_exists($protectedFile)) {
            return new PathsConfig(self::defaults(false), []);
        }

        $data = JsonUtils::readFile($protectedFile, true);
        return new PathsConfig(self::defaults(false), $data);
    }

    /**
     * Načte konfiguraci z projectRoot. Vyžaduje existenci konfiguračního souboru.
     *
     * @throws \RuntimeException Pokud soubor neexistuje
     */
    public static function loadOrFail(string $projectRoot): PathsConfig
    {
        $protectedFile = self::normalizePath($projectRoot) . DIRECTORY_SEPARATOR . PathsConfig::CONFIG_FILE_PROTECTED;

        if (!file_exists($protectedFile)) {
            throw new \RuntimeException(
                'Run "php vendor/bin/platformbridge init" to generate ' . PathsConfig::CONFIG_FILE_PROTECTED,
            );
        }

        $data = JsonUtils::readFile($protectedFile, true);
        return new PathsConfig(self::defaults(true), $data);
    }

    /**
     * Vrátí výchozí cesty podle režimu (vendor vs standalone).
     *
     * Toto je single source of truth pro výchozí konfiguraci cest.
     * Používá se jak při načítání (merge s uživatelskou konfigurací),
     * tak při generování nového konfiguračního souboru.
     */
    private static function defaults(bool $isVendor): array
    {
        if ($isVendor) {
            return [
                PathsConfig::KEY_ASSETS_PATH     => 'public/assets',
                PathsConfig::KEY_BRIDGE_CONFIG   => 'public/config/' . PathsConfig::STUB_BRIDGE,
                PathsConfig::KEY_SECURITY_CONFIG => 'public/config/' . PathsConfig::STUB_SECURITY,
                PathsConfig::KEY_CACHE_PATH      => 'var/cache',
                PathsConfig::KEY_API_FILE        => 'public/' . PathsConfig::STUB_API,
            ];
        }

        return [
            PathsConfig::KEY_ASSETS_PATH     => 'dist',
            PathsConfig::KEY_BRIDGE_CONFIG   => 'dev/' . PathsConfig::STUB_BRIDGE,
            PathsConfig::KEY_SECURITY_CONFIG => 'dev/' . PathsConfig::STUB_SECURITY,
            PathsConfig::KEY_CACHE_PATH      => 'var/cache',
            PathsConfig::KEY_API_FILE        => 'dev/' . PathsConfig::STUB_API,
        ];
    }

    /**
     * Vrátí výchozí konfiguraci jako formátovaný JSON string.
     *
     * Slouží pro generování konfiguračního souboru platformbridge.json.php
     * bez nutnosti stub souboru – defaults() je single source of truth.
     */
    public static function defaultsAsJson(bool $isVendor): string
    {
        return json_encode(self::defaults($isVendor), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
