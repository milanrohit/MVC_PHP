<?php

declare(strict_types=1);

namespace app\Support;

use PDO;
use PDOStatement;
use InvalidArgumentException;
use RuntimeException;

/**
 * SOLID Design: Single Responsibility Principle (SRP) for database interaction abstractions.
 * Optimization: Prepared statement reuse, strict type safety, and memory-conscious streaming fetches.
 */
final class SqlHelper
{
    private static ?PDO $pdo = null;
    private static array $statementCache = [];

    /**
     * Instantiation is blocked to enforce an optimized, unified static interface.
     */
    private function __construct() {}

    /**
     * 1. Initialize the helper with a shared PDO instance.
     */
    public static function setConnection(PDO $pdo): void
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        self::$pdo = $pdo;
    }

    /**
     * 2. Ensure connection exists and return it safely.
     */
    private static function getPdo(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException("SqlHelper Connection has not been initialized. Call setConnection() first.");
        }
        return self::$pdo;
    }

    /**
     * 3. Prepare and cache statements to eliminate parsing overhead on repeated queries.
     */
    private static function getPreparedStatement(string $sql): PDOStatement
    {
        $hash = md5($sql);
        if (!isset(self::$statementCache[$hash])) {
            self::$statementCache[$hash] = self::getPdo()->prepare($sql);
        }
        return self::$statementCache[$hash];
    }

    /**
     * 4. Fetch ALL rows from a query at once.
     */
    public static function getAll(string $sql, array $params = []): array
    {
        $stmt = self::getPreparedStatement($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 5. Fetch a SINGLE row from a query. Returns null if no record is found.
     */
    public static function getOneRow(string $sql, array $params = []): ?array
    {
        $stmt = self::getPreparedStatement($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 6. Fetch a SINGLE column value from a single row (e.g., COUNT(*), MAX(id)).
     */
    public static function getValue(string $sql, array $params = []): mixed
    {
        $stmt = self::getPreparedStatement($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * 7. High-Performance Stream Fetch: Iterates through records line-by-line using standard PHP Generators.
     * Prevents out-of-memory crashes when dealing with millions of records.
     */
    public static function stream(string $sql, array $params = []): \Generator
    {
        $stmt = self::getPdo()->prepare($sql); // Do not cache streaming queries to allow execution recycling
        $stmt->execute($params);
        
        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }

    /**
     * 8. DML: INSERT a record into a table cleanly using key-value array pairing.
     */
    public static function insert(string $table, array $data): string|int
    {
        if (empty($data)) {
            throw new InvalidArgumentException("Cannot execute an insert with an empty dataset.");
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = self::getPreparedStatement($sql);
        $stmt->execute($data);

        return self::getPdo()->lastInsertId();
    }

    /**
     * 9. DML: UPDATE records in a table matching structured conditions.
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (empty($data)) {
            throw new InvalidArgumentException("Cannot execute an update with an empty dataset.");
        }

        $assignments = [];
        $executeParams = [];

        foreach ($data as $column => $value) {
            $assignments[] = "{$column} = :update_{$column}";
            $executeParams["update_{$column}"] = $value;
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $assignments),
            $where
        );

        $stmt = self::getPreparedStatement($sql);
        $stmt->execute(array_merge($executeParams, $whereParams));

        return $stmt->rowCount();
    }

    /**
     * 10. DML: DELETE records matching structured conditions.
     */
    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
        
        $stmt = self::getPreparedStatement($sql);
        $stmt->execute($whereParams);
        
        return $stmt->rowCount();
    }

    /**
     * 11. Transaction: Start a database transaction block wrapper.
     */
    public static function beginTransaction(): bool
    {
        return self::getPdo()->beginTransaction();
    }

    /**
     * 12. Transaction: Commit changes safely to the storage layer.
     */
    public static function commit(): bool
    {
        return self::getPdo()->commit();
    }

    /**
     * 13. Transaction: Rollback changes instantly if any problem arises.
     */
    public static function rollBack(): bool
    {
        return self::getPdo()->rollBack();
    }

    /**
     * 14. Transaction Wrapper: Safely executes a callback inside a bulletproof transactional container.
     */
    public static function transactional(callable $callback): mixed
    {
        self::beginTransaction();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    /**
     * 15. Clear out statement compilation caches to free server engine memory profiles.
     */
    public static function clearCache(): void
    {
        self::$statementCache = [];
    }
}
