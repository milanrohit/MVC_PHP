<?php
declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;
use RuntimeException;

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
        $driver  = $c['driver'] ?? 'mysql';
        $charset = $c['charset'] ?? 'utf8mb4';
        $port    = (int) ($c['port'] ?? 3306);

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $driver, $c['host'], $port, $c['database'], $charset
        );

        try {
            return new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // strict, real prepared statements
                PDO::ATTR_TIMEOUT            => 5,     // fail fast instead of hanging
            ]);
        } catch (PDOException $e) {
            // Show WHERE it tried to connect (never the password) so config mistakes are obvious.
            throw new RuntimeException(sprintf(
                'Database connection failed (%s@%s:%d/%s): %s',
                $c['username'], $c['host'], $port, $c['database'], $e->getMessage()
            ), 0, $e);
        }
    }
}
