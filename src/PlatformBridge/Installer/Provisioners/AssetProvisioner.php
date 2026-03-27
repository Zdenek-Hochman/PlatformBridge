<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Installer\Provisioners;

use Zoom\PlatformBridge\Installer\Publisher\StubPublisher;
use Zoom\PlatformBridge\Installer\Publisher\PublishResult;

/**
 * Publikuje dist assety (JS/CSS) do hostující aplikace.
 *
 * Assety se VŽDY přepisují – nejsou uživatelem upravovány,
 * při každém update/install se nahradí nejnovější buildovou verzí.
 *
 * Oddělena od Installeru pro jasné oddělení zodpovědností.
 *
 * @see StubPublisher::publishDirectory() Pro nízkoúrovňové kopírování
 * @see PublishResult                     Pro strukturovaný výstup
 */
final class AssetProvisioner
{
    /**
     * @param string $distPath   Absolutní cesta k dist/ adresáři balíčku
     * @param string $targetPath Absolutní cesta k cílovému adresáři assetů
     * @param string $label      Relativní cesta pro CLI výpis (z PathsConfig)
     */
    public function __construct(
        private readonly string $distPath,
        private readonly string $targetPath,
        private readonly string $label,
    ) {}

    // ─── Akce ────────────────────────────────────────────────

    /**
     * Publikuje JS a CSS podadresáře z dist/ do cílové složky.
     *
     * Každý podadresář se publikuje s overwrite: true – assety jsou
     * build artefakty generované npm run build, ne uživatelské soubory.
     *
     * Pokud zdrojový podadresář neexistuje (build nebyl spuštěn),
     * vrátí PublishResult::missing() s nápovědou.
     *
     * @param StubPublisher $publisher Publisher pro bezpečné kopírování
     * @return list<PublishResult> Výsledek pro každý podadresář (js, css)
     */
    public function provision(StubPublisher $publisher): array
    {
        $results = [];

        foreach (['js', 'css'] as $dir) {
            $source = $this->distPath . DIRECTORY_SEPARATOR . $dir;

            if (is_dir($source)) {
                $count = $publisher->publishDirectory(
                    $source,
                    $this->targetPath . DIRECTORY_SEPARATOR . $dir,
                    overwrite: true,
                );
                $results[] = PublishResult::published($this->label . '/' . $dir, $count);
            } else {
                $results[] = PublishResult::missing(
                    'dist/' . $dir,
                    "Run 'npm run build' first to generate assets.",
                );
            }
        }

        return $results;
    }

    // ─── Dotazy na stav ──────────────────────────────────────

    /**
     * Zda existuje dist/ adresář s build artefakty.
     */
    public function distExists(): bool
    {
        return is_dir($this->distPath);
    }

    /**
     * Zda existuje konkrétní podadresář (js/css) v dist/.
     */
    public function subDirExists(string $dir): bool
    {
        return is_dir($this->distPath . DIRECTORY_SEPARATOR . $dir);
    }
}
