<?php

declare(strict_types=1);

namespace ProjectDesk\Infrastructure\Sqlite;

use PDO;
use PDOStatement;
use RuntimeException;

final readonly class Sqlite
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param list<int|string|null> $parameters */
    public function execute(string $query, array $parameters = []): PDOStatement
    {
        $statement = $this->pdo->prepare($query);
        if ($statement === false || !$statement->execute($parameters)) {
            throw new RuntimeException('SQLite statement failed.');
        }

        return $statement;
    }

    /**
     * @param list<int|string|null> $parameters
     * @return array<string, mixed>|null
     */
    public function one(string $query, array $parameters = []): ?array
    {
        $row = $this->execute($query, $parameters)->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param list<int|string|null> $parameters
     * @return list<array<string, mixed>>
     */
    public function all(string $query, array $parameters = []): array
    {
        $statement = $this->execute($query, $parameters);
        $rows = [];
        while (is_array($row = $statement->fetch(PDO::FETCH_ASSOC))) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    public static function integer(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (!is_int($value)) {
            throw new RuntimeException("Expected an integer in SQLite column {$column}.");
        }
        return $value;
    }

    /** @param array<string, mixed> $row */
    public static function text(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException("Expected text in SQLite column {$column}.");
        }
        return $value;
    }
}
