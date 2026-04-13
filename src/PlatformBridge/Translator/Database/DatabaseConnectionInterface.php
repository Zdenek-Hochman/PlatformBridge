<?php
declare(strict_types=1);

namespace PlatformBridge\Translator\Database;

interface DatabaseConnectionInterface
{
    public function fetchAll(string $sql, array $params = [], string $types = ''): array;
    public function fetchColumn(string $sql, array $params = [], string $types = ''): array;
    public function execute(string $sql, array $params = [], string $types = ''): int;
}