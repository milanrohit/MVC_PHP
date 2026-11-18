<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Exceptions\SqlHelperConnectionException;
use Generator;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Single Responsibility Principle (SRP) helper for database interaction abstractions.
 */
final class SqlHelper
{
    private const string IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/';

    private static ?PDO $pdo = null;

    /** @var array<string, PDOStatement> */
    private static array $statementCache = [];

    /**
     * Instantiation is blocked to enforce an optimized, unified static interface.
     */
    private function __construct()
    {
    }

    /**
     * Initialize the helper with a shared PDO instance.
     */
    public static function setConnection(PDO $pdo): void
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        self::$pdo = $pdo;
    }

    /**
     * Fetch ALL rows from a query at once.
     *
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public static function getAll(string $sql, array $params = []): array
    {
        $stmt = self::getPreparedStatement($sql);
        $stmt->execute($params);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Fetch a SINGLE row from a query. Returns null if no record is found.
     *
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function getOneRow(string $sql, array $params = []): ?array
    {
        $stmt = self::getPreparedStatement($sql);
        $stmt->execute($params);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return \is_array($result) ? $result : null;
    }

    /**
     * Fetch a SINGLE column value from a single row (e.g., COUNT(*), MAX(id)).
     *
     * @param array<string|int, mixed> $params
     */
    public static function getValue(string $sql, array $params = []): mixed
    {
        $stmt = self::getPreparedStatement($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    /**
     * Stream records line-by-line using PHP Generators to prevent out-of-memory crashes.
     *
     * @param array<string|int, mixed> $params
     * @return Generator<int, array<string, mixed>>
     */
    public static function stream(string $sql, array $params = []): Generator
    {
        $stmt = self::getPdo()->prepare($sql);
        $stmt->execute($params);

        /** @var array<string, mixed>|false $row */
        while (($row = $stmt->fetch()) !== false) {
            yield $row;
        }
    }

    /**
     * INSERT a record into a table cleanly using key-value array pairing.
     *
     * @param array<string, mixed> $data
     */
    public static function insert(string $table, array $data): string|int
    {
        if ($data === []) {
            throw new InvalidArgumentException('Cannot execute an insert with an empty dataset.');
        }

        $safeTable = self::quoteIdentifier($table);
        $columns = array_keys($data);
        $quotedColumns = array_map(self::quoteIdentifier(...), $columns);
        $placeholders = array_map(static fn (string $col): string => ":{$col}", $columns);

        $sql = \sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $safeTable,
            implode(', ', $quotedColumns),
            implode(', ', $placeholders),
        );

        $stmt = self::getPreparedStatement($sql);
        $stmt->execute($data);

        return (string) self::getPdo()->lastInsertId();
    }

    /**
     * UPDATE records in a table matching structured conditions.
     *
     * @param array<string, mixed> $data
     * @param array<string|int, mixed> $whereParams
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if ($data === []) {
            throw new InvalidArgumentException('Cannot execute an update with an empty dataset.');
        }

        if (trim($where) === '') {
            throw new InvalidArgumentException('Update statement requires a non-empty WHERE clause.');
        }

        $safeTable = self::quoteIdentifier($table);
        $assignments = [];
        $executeParams = [];

        foreach ($data as $column => $value) {
            $safeColumn = self::quoteIdentifier($column);
            $assignments[] = "{$safeColumn} = :update_{$column}";
            $executeParams["update_{$column}"] = $value;
        }

        $sql = \sprintf(
            'UPDATE %s SET %s WHERE %s',
            $safeTable,
            implode(', ', $assignments),
            $where,
        );

        $stmt = self::getPreparedStatement($sql);
        $stmt->execute(array_merge($executeParams, $whereParams));

        return $stmt->rowCount();
    }

    /**
     * DELETE records matching structured conditions.
     *
     * @param array<string|int, mixed> $whereParams
     */
    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        if (trim($where) === '') {
            throw new InvalidArgumentException('Delete statement requires a non-empty WHERE clause.');
        }

        $safeTable = self::quoteIdentifier($table);
        $sql = \sprintf('DELETE FROM %s WHERE %s', $safeTable, $where);

        $stmt = self::getPreparedStatement($sql);
        $stmt->execute($whereParams);

        return $stmt->rowCount();
    }

    /**
     * Start a database transaction block wrapper.
     */
    public static function beginTransaction(): bool
    {
        return self::getPdo()->beginTransaction();
    }

    /**
     * Commit changes safely to the storage layer.
     */
    public static function commit(): bool
    {
        return self::getPdo()->commit();
    }

    /**
     * Rollback changes instantly if any problem arises.
     */
    public static function rollBack(): bool
    {
        return self::getPdo()->rollBack();
    }

    /**
     * Safely execute a callback inside a transactional container.
     *
     * @template TReturn
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    public static function transactional(callable $callback): mixed
    {
        self::beginTransaction();

        try {
            $result = $callback();
            self::commit();

            return $result;
        } catch (Throwable $e) {
            self::rollBack();

            throw $e;
        }
    }

    /**
     * Clear statement compilation caches to free memory.
     */
    public static function clearCache(): void
    {
        self::$statementCache = [];
    }

    private static function quoteIdentifier(string $identifier): string
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new InvalidArgumentException("Invalid SQL identifier: '{$identifier}'.");
        }

        return '`' . $identifier . '`';
    }

    private static function getPdo(): PDO
    {
        if (self::$pdo === null) {
            throw new SqlHelperConnectionException();
        }

        return self::$pdo;
    }

    private static function getPreparedStatement(string $sql): PDOStatement
    {
        $hash = hash('xxh128', $sql);
        if (!isset(self::$statementCache[$hash])) {
            self::$statementCache[$hash] = self::getPdo()->prepare($sql);
        }

        return self::$statementCache[$hash];
    }
}
