<?php

declare(strict_types=1);

namespace PlatformBridge\Installer\Publisher;

use PlatformBridge\Security\JsonGuard;

/**
 * Bezpečné publikování souborů se skip logikou.
 *
 * Pravidla:
 *   - overwrite: true  → soubor se VŽDY přepíše (assety)
 *   - overwrite: false → soubor se přeskočí pokud existuje (konfigurace)
 */
final class StubPublisher
{
    /** @var list<array{source: string, target: string, skipped: bool}> */
    private array $published = [];

    /**
     * Publikuje soubor. Vrátí true pokud byl soubor zapsán.
     */
    public function publish(string $source, string $target, bool $overwrite = false): bool
    {
        if (!file_exists($source)) {
            throw new \RuntimeException("Source file not found: {$source}");
        }

        if (!$overwrite && file_exists($target)) {
            $this->published[] = ['source' => $source, 'target' => $target, 'skipped' => true];
            return false;
        }

        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        copy($source, $target);
        $this->published[] = ['source' => $source, 'target' => $target, 'skipped' => false];
        return true;
    }

    /**
     * Publikuje celý adresář.
     */
    public function publishDirectory(string $source, string $target, bool $overwrite = false): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relative = substr($item->getPathname(), strlen($source) + 1);
                if ($this->publish($item->getPathname(), $target . DIRECTORY_SEPARATOR . $relative, $overwrite)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Publikuje JSON soubor s PHP exit guardem (ochrana proti web přístupu).
     *
     * Přečte zdrojový JSON, obalí ho PHP exit guardem a zapíše do cíle.
     * Cílový soubor by měl mít příponu .json.php.
     *
     * @param string $source Zdrojový .json soubor
     * @param string $target Cílový .json.php soubor
     * @param bool $overwrite Přepsat existující soubor
     * @return bool True pokud byl soubor zapsán
     */
    public function publishProtected(string $source, string $target, bool $overwrite = false): bool
    {
        if (!file_exists($source)) {
            throw new \RuntimeException("Source file not found: {$source}");
        }

        if (!$overwrite && file_exists($target)) {
            $this->published[] = ['source' => $source, 'target' => $target, 'skipped' => true];
            return false;
        }

        $jsonContent = file_get_contents($source);
        if ($jsonContent === false) {
            throw new \RuntimeException("Cannot read source file: {$source}");
        }

        // Pokud zdroj již obsahuje guard (re-publish), extrahuj čistý JSON
        $jsonContent = JsonGuard::strip($jsonContent);

        JsonGuard::writeProtected($target, $jsonContent);
        $this->published[] = ['source' => $source, 'target' => $target, 'skipped' => false];
        return true;
    }

    /**
     * Publikuje soubor s nahrazením placeholderů v obsahu.
     *
     * Čte zdrojový soubor, provede str_replace na zadaných párech
     * a zapíše výsledek do cíle. Skip logika je stejná jako u publish().
     *
     * @param string               $source       Zdrojový stub soubor
     * @param string               $target       Cílový soubor
     * @param array<string,string>  $replacements Mapa placeholder → hodnota
     * @param bool                  $overwrite    Přepsat existující soubor
     * @return bool True pokud byl soubor zapsán
     */
    public function publishWithReplacements(
        string $source,
        string $target,
        array $replacements,
        bool $overwrite = false,
    ): bool {
        if (!file_exists($source)) {
            throw new \RuntimeException("Source file not found: {$source}");
        }

        if (!$overwrite && file_exists($target)) {
            $this->published[] = ['source' => $source, 'target' => $target, 'skipped' => true];
            return false;
        }

        $content = file_get_contents($source);
        if ($content === false) {
            throw new \RuntimeException("Cannot read source file: {$source}");
        }

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $content,
        );

        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($target, $content);
        $this->published[] = ['source' => $source, 'target' => $target, 'skipped' => false];
        return true;
    }

    /** @return list<array{source: string, target: string, skipped: bool}> */
    public function getLog(): array
    {
        return $this->published;
    }
}
