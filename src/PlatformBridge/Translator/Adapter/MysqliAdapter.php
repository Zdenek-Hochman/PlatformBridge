<?php

declare(strict_types=1);

namespace PlatformBridge\Translator\Adapter;

use PlatformBridge\Translator\Database\MysqliConnection;
use PlatformBridge\Translator\Database\TableProvisioner;
use PlatformBridge\Translator\Domain;

/**
 * Vestavěný MySQL adaptér pro tabulku pb_translations.
 *
 * Načítá překlady z interní tabulky, kterou PlatformBridge sám vytvořil.
 * Podporuje auto-provisioning tabulky přes TableProvisioner.
 *
 * @see docs/TRANSLATOR-PROPOSAL-V2.md §4.2
 */
final class MysqliAdapter implements TranslationAdapterInterface
{
    public function __construct(
        private readonly MysqliConnection $connection,
        private readonly string $tableName = 'pb_translations',
    ) {}

    /**
     * Factory — vytvoří adaptér z mysqli instance.
     * Volitelně zajistí existenci tabulky (auto-provisioning).
     *
     * @param \mysqli $mysqli Existující mysqli připojení
     * @param string $tableName Název tabulky (default: 'pb_translations')
     * @param bool $ensureTable Automaticky vytvořit tabulku pokud neexistuje
     * @return self Nový adaptér
     */
    public static function fromMysqli(
        \mysqli $mysqli,
        string $tableName = 'pb_translations',
        bool $ensureTable = true,
    ): self {
        if ($ensureTable) {
            (new TableProvisioner($mysqli, $tableName))->ensure();
        }

        return new self(new MysqliConnection($mysqli), $tableName);
    }

    /**
     * {@inheritDoc}
     *
     * Načte překlady pro daný locale a doménu z tabulky pb_translations.
     */
    public function fetch(string $locale, Domain $domain): array
    {
        $sql = sprintf(
            'SELECT `key_path`, `value` FROM %s WHERE `locale` = ? AND `domain` = ?',
            $this->escapeIdentifier($this->tableName)
        );

        $rows = $this->connection->fetchAll($sql, [$locale, $domain->value], 'ss');

        $result = [];
        foreach ($rows as $row) {
            $result[$row['key_path']] = $row['value'];
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function availableLocales(): array
    {
        $sql = sprintf(
            'SELECT DISTINCT `locale` FROM %s',
            $this->escapeIdentifier($this->tableName)
        );

        return $this->connection->fetchColumn($sql);
    }

    /**
     * Escapuje identifikátor pro SQL.
     */
    private function escapeIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
