<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Installer;

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

    /** @return list<array{source: string, target: string, skipped: bool}> */
    public function getLog(): array
    {
        return $this->published;
    }
}
