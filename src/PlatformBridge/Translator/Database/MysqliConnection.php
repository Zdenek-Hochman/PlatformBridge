<?php

declare(strict_types=1);

namespace PlatformBridge\Translator\Database;

/**
 * mysqli implementace DatabaseConnectionInterface.
 *
 * Obaluje mysqli prepared statements do jednoduchého API.
 * Používá se výhradně pro interní tabulku pb_translations.
 */
final class MysqliConnection implements DatabaseConnectionInterface
{
    public function __construct(
        private readonly \mysqli $mysqli,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function fetchAll(string $sql, array $params = [], string $types = ''): array
    {
        $stmt = $this->prepare($sql, $params, $types);

        if ($stmt->execute() === false) {
            throw new \RuntimeException('Query failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result === false) {
            throw new \RuntimeException('get_result failed: ' . $stmt->error);
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchColumn(string $sql, array $params = [], string $types = ''): array
    {
        $rows = $this->fetchAll($sql, $params, $types);

        return array_map(
            fn(array $row) => (string) reset($row),
            $rows
        );
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $params = [], string $types = ''): int
    {
        $stmt = $this->prepare($sql, $params, $types);

        if ($stmt->execute() === false) {
            throw new \RuntimeException('Execute failed: ' . $stmt->error);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * Připraví prepared statement a naváže parametry.
     *
     * @param string $sql SQL dotaz
     * @param array $params Parametry
     * @param string $types Typy pro bind_param
     * @return \mysqli_stmt Připravený statement
     * @throws \RuntimeException Pokud prepare selže
     */
    private function prepare(string $sql, array $params, string $types): \mysqli_stmt
    {
        $stmt = $this->mysqli->prepare($sql);

        if ($stmt === false) {
            throw new \RuntimeException('Prepare failed: ' . $this->mysqli->error);
        }

        if (!empty($params)) {
            if ($types === '') {
                $types = str_repeat('s', count($params));
            }
            $stmt->bind_param($types, ...$params);
        }

        return $stmt;
    }
}
