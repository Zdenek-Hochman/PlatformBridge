<?php

declare(strict_types=1);

namespace PlatformBridge\Asset;

/**
 * Správce assetů (JS/CSS) s logikou "once".
 *
 * Zajišťuje, že se assety vloží do stránky pouze jednou,
 * bez ohledu na počet vykreslených formulářů.
 *
 * Cesta k assetům se auto-detekuje z DOCUMENT_ROOT:
 *   - doc root = project/public  → /platformbridge
 *   - doc root = project root    → /public/platformbridge
 *   - doc root = parent, projekt v podsložce → /{basePath}/public/platformbridge
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
     * @param string $baseUrl Základní URL ke složce platformbridge (např. '/public/platformbridge' nebo '/platformbridge')
     *                        Složka musí obsahovat podsložky js/ a css/ s příslušnými soubory.
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

        $url = $this->baseUrl . '/js/pb-main.js?v=' . time();
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

        $url = $this->baseUrl . '/css/pb-main.css?v=' . time();
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
     * Resetuje stav vykreslení (užitečné pro testy).
     */
    public static function reset(): void
    {
        self::$rendered = [
            'scripts' => false,
            'styles' => false,
        ];
    }
}
