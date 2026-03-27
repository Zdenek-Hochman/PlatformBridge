<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Installer\Publisher;

/**
 * Výsledek publish/provisioning operace.
 *
 * Univerzální result objekt používaný provisioner třídami
 * pro strukturovaný výstup instalačních kroků.
 *
 * Každý provisioner vrací instanci nebo pole instancí PublishResult,
 * Installer pak pouze zavolá {@see message()} pro CLI výpis.
 *
 * @see FileProvisioner
 * @see AssetProvisioner
 * @see DirectoryProvisioner
 */
final class PublishResult
{
    public const PUBLISHED = 'published';
    public const SKIPPED   = 'skipped';
    public const CREATED   = 'created';
    public const GUARDED   = 'guarded';
    public const EXISTS    = 'exists';
    public const MISSING   = 'missing';

    private function __construct(
        public readonly string $label,
        public readonly string $status,
        public readonly ?int $count = null,
        public readonly ?string $hint = null,
    ) {}

    // ─── Factory methods ─────────────────────────────────────

    /**
     * Soubor/adresář byl publikován (nový nebo přepsaný).
     *
     * @param string   $label Relativní cesta / popis pro CLI
     * @param int|null $count Počet souborů (pro adresářové publish)
     */
    public static function published(string $label, ?int $count = null): self
    {
        return new self($label, self::PUBLISHED, $count);
    }

    /**
     * Soubor přeskočen – existuje a force není aktivní.
     */
    public static function skipped(string $label): self
    {
        return new self($label, self::SKIPPED);
    }

    /**
     * Adresář nebo soubor byl vytvořen.
     */
    public static function created(string $label): self
    {
        return new self($label, self::CREATED);
    }

    /**
     * Ochranný guard soubor (index.php) byl vytvořen.
     */
    public static function guarded(string $label): self
    {
        return new self($label, self::GUARDED);
    }

    /**
     * Adresář/položka již existuje – žádná akce.
     */
    public static function exists(string $label): self
    {
        return new self($label, self::EXISTS);
    }

    /**
     * Zdrojový soubor/adresář nenalezen.
     *
     * @param string      $label Popis chybějícího zdroje
     * @param string|null $hint  Nápověda pro uživatele (např. "Run npm run build")
     */
    public static function missing(string $label, ?string $hint = null): self
    {
        return new self($label, self::MISSING, hint: $hint);
    }

    // ─── Queries ─────────────────────────────────────────────

    /**
     * Zda operace provedla zápis na disk.
     */
    public function isWritten(): bool
    {
        return in_array($this->status, [self::PUBLISHED, self::CREATED, self::GUARDED], true);
    }

    // ─── Output ──────────────────────────────────────────────

    /**
     * Lidsky čitelná zpráva pro CLI výstup.
     */
    public function message(): string
    {
        return match ($this->status) {
            self::PUBLISHED => $this->count !== null
                ? "  ✅ Published: {$this->label} ({$this->count} files)"
                : "  ✅ Published: {$this->label}",
            self::SKIPPED   => "  ⏭️  Skipped:   {$this->label} (exists)",
            self::CREATED   => "  📁 Created:   {$this->label}/",
            self::GUARDED   => "  🔒 Guard:     {$this->label}",
            self::EXISTS    => "  ✅ {$this->label}",
            self::MISSING   => $this->hint !== null
                ? "  ⚠️  Missing:   {$this->label}\n     {$this->hint}"
                : "  ⚠️  Missing:   {$this->label}",
        };
    }
}
