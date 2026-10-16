<?php
declare(strict_types=1);

namespace Core;

use PDO;

/** PDO Singleton (shared connection) + Factory (make from any config array). */
final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function connection(): PDO
    {
        return self::$instance ??= self::make((array) Config::get('database'));
    }

    /** Factory */
    public static function make(array $c): PDO
    {
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $c['driver'] ?? 'mysql', $c['host'], $c['port'], $c['database'], $c['charset'] ?? 'utf8mb4'
        );

        return new PDO($dsn, $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // strict, real prepared statements
        ]);
    }
}
