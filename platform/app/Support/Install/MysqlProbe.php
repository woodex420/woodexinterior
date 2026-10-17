<?php

declare(strict_types=1);

namespace App\Support\Install;

use PDO;

final class MysqlProbe implements ProbesDatabase
{
    public function probe(array $db): array
    {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
            $db['username'],
            $db['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );

        $tables = ($statement = $pdo->query('SHOW TABLES')) !== false ? $statement->fetchAll(PDO::FETCH_COLUMN) : [];

        return [
            'version' => (string) $pdo->query('SELECT VERSION()')?->fetchColumn(),
            'tables' => array_values(array_map('strval', $tables)),
            'user' => (string) $pdo->query('SELECT CURRENT_USER()')?->fetchColumn(),
        ];
    }
}
