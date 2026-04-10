<?php

declare(strict_types=1);

namespace PlatformBridge\Translator\Database;

/**
 * Zajišťuje existenci tabulky pb_translations.
 *
 * Bezpečný pro opakované volání (idempotentní).
 * Vytvoří tabulku s překladovým schema pokud neexistuje.
 *
 * @see docs/TRANSLATOR-PROPOSAL-V2.md §1.3
 */
final class TableProvisioner
{
    private const DEFAULT_TABLE = 'pb_translations';

    public function __construct(
        private readonly \mysqli $mysqli,
        private readonly string $tableName = self::DEFAULT_TABLE,
    ) {}

    /**
     * Ověří zda tabulka existuje a případně ji vytvoří.
     *
     * @return bool true = tabulka byla právě vytvořena, false = již existovala
     */
    public function ensure(): bool
    {
        if ($this->exists()) {
            return false;
        }

        $this->create();
        return true;
    }

    /**
     * Kontroluje existenci tabulky.
     */
    public function exists(): bool
    {
        $escaped = $this->mysqli->real_escape_string($this->tableName);
        $result = $this->mysqli->query(
            "SHOW TABLES LIKE '{$escaped}'"
        );

        return $result !== false && $result->num_rows > 0;
    }

    /**
     * Vytvoří tabulku s překladovým schema.
     *
     * @throws \RuntimeException Pokud CREATE selže
     */
    private function create(): void
    {
        $table = $this->escapeIdentifier($this->tableName);

        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS {$table} (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `locale`     VARCHAR(10)  NOT NULL,
            `domain`     VARCHAR(20)  NOT NULL,
            `key_path`   VARCHAR(255) NOT NULL,
            `value`      TEXT         NOT NULL,
            `hash`       CHAR(8)      NOT NULL,
            `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_translation` (`locale`, `domain`, `key_path`),
            INDEX `idx_locale_domain` (`locale`, `domain`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        if ($this->mysqli->query($sql) === false) {
            throw new \RuntimeException(
                "Failed to create translations table: " . $this->mysqli->error
            );
        }
    }

    /**
     * Escapuje identifikátor (název tabulky/sloupce) pro použití v SQL.
     */
    private function escapeIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
