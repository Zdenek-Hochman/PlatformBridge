<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Installer\Provisioners;

use Zoom\PlatformBridge\Paths\PathsConfig;

/**
 * Výsledek provisioning operace konfiguračního souboru.
 *
 * Umožňuje Installeru poskytnout uživateli srozumitelný výstup
 * bez znalosti interní logiky ConfigProvisioneru.
 */
enum ProvisionResult: string
{
    /** Starý .json zmigrován na .json.php */
    case Migrated = 'migrated';

    /** Stub publikován jako nový .json.php */
    case Published = 'published';

    /** Soubor již existuje, žádná akce */
    case Skipped = 'skipped';

    /**
     * Vrátí lidsky čitelnou zprávu pro CLI výstup.
     */
    public function message(): string
    {
        $label = PathsConfig::CONFIG_FILE_PROTECTED;

        return match ($this) {
            self::Migrated  => "  🔒 Migrated: " . PathsConfig::CONFIG_FILE . " → {$label}\n"
                             . "     Starý nechráněný soubor " . PathsConfig::CONFIG_FILE . " byl odstraněn.",
            self::Published => "  ✅ Published: {$label} (secured with PHP exit guard)",
            self::Skipped   => "  ⏭️  Skipped:   {$label} (exists – user config preserved)",
        };
    }

    /**
     * Zda po této operaci je nutné znovu načíst PathResolver.
     */
    public function requiresReload(): bool
    {
        return match ($this) {
            self::Migrated, self::Published => true,
            self::Skipped => false,
        };
    }
}
