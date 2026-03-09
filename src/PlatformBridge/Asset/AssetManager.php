<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Asset;

/**
 * Správce assetů (JS/CSS) s logikou "once".
 *
 * Zajišťuje, že se assety vloží do stránky pouze jednou,
 * bez ohledu na počet vykreslených formulářů.
 */
final class AssetManager
{
    /**
     * Sleduje, které assety již byly vykresleny.
     */
    private static array $rendered = [
        'scripts' => false,
        'styles' => false,
    ];

    /**
     * Základní URL pro načítání assetů.
     */
    private string $baseUrl;

    /**
     * @param string $baseUrl URL k asset endpointu (např. '/vendor/zoom/platform-bridge/assets/dist/serve.php')
     */
    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Vrátí JavaScript <script> tag - pouze při prvním volání.
     *
     * @param bool $force Vynutí vrácení i když už bylo voláno
     * @return string HTML <script> tag nebo prázdný string
     */
    public function getScripts(bool $force = false): string
    {
        if (self::$rendered['scripts'] && !$force) {
            return '';
        }

        self::$rendered['scripts'] = true;

        $url = $this->buildAssetUrl('js', 'main.js');
        return sprintf('<script src="%s"></script>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Vrátí CSS <link> tag - pouze při prvním volání.
     *
     * @param bool $force Vynutí vrácení i když už bylo voláno
     * @return string HTML <link> tag nebo prázdný string
     */
    public function getStyles(bool $force = false): string
    {
        if (self::$rendered['styles'] && !$force) {
            return '';
        }

        self::$rendered['styles'] = true;

        $url = $this->buildAssetUrl('css', 'main.css');
        return sprintf('<link rel="stylesheet" href="%s">', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Vrátí všechny assety (styly + skripty) - každý pouze jednou.
     *
     * @return string HTML s CSS a JS tagy
     */
    public function getAssets(): string
    {
        $output = [];

        $styles = $this->getStyles();
        if ($styles !== '') {
            $output[] = $styles;
        }

        $scripts = $this->getScripts();
        if ($scripts !== '') {
            $output[] = $scripts;
        }

        return implode("\n", $output);
    }

    /**
     * Zkontroluje, zda už byly skripty vykresleny.
     */
    public static function scriptsRendered(): bool
    {
        return self::$rendered['scripts'];
    }

    /**
     * Zkontroluje, zda už byly styly vykresleny.
     */
    public static function stylesRendered(): bool
    {
        return self::$rendered['styles'];
    }

    /**
     * Sestaví URL pro konkrétní asset.
     */
    private function buildAssetUrl(string $type, string $filename): string
    {
        return sprintf('%s?type=%s&file=%s', $this->baseUrl, $type, $filename);
    }
}
