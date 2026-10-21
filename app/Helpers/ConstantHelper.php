<?php

declare(strict_types=1);

namespace app\Support;

/**
 * SOLID Design: Single Responsibility Principle (SRP) for compiling core framework configuration and immutable tokens.
 * Optimization: Uses strict PHP 8.4 constant type hints which resolve at the compiler level for maximum speed.
 */
final class ConstantHelper
{
    /**
     * Instantiation is blocked to enforce a strict, immutable dictionary catalog.
     */
    private function __construct() {}

    // =========================================================================
    // 1. APPLICATION META & SPECS
    // =========================================================================
    public const string APP_VERSION = '2.4.0';
    public const string DEFAULT_ENCODING = 'UTF-8';
    public const string DEFAULT_TIMEZONE = 'Australia/Sydney';

    // =========================================================================
    // 2. HTTP RESPONSE STATUS CODES
    // =========================================================================
    public const int HTTP_OK = 200;
    public const int HTTP_CREATED = 201;
    public const int HTTP_ACCEPTED = 202;
    public const int HTTP_NO_CONTENT = 204;
    public const int HTTP_BAD_REQUEST = 400;
    public const int HTTP_UNAUTHORIZED = 401;
    public const int HTTP_FORBIDDEN = 403;
    public const int HTTP_NOT_FOUND = 404;
    public const int HTTP_METHOD_NOT_ALLOWED = 405;
    public const int HTTP_VALIDATION_FAILED = 422;
    public const int HTTP_INTERNAL_SERVER_ERROR = 500;

    // =========================================================================
    // 3. CORE SYSTEM ENVIRONMENTS
    // =========================================================================
    public const string ENV_PRODUCTION = 'production';
    public const string ENV_STAGING = 'staging';
    public const string ENV_DEVELOPMENT = 'development';
    public const string ENV_TESTING = 'testing';

    // =========================================================================
    // 4. DATABASE & DML OPERATIONS
    // =========================================================================
    public const string DB_DRIVER_MYSQL = 'mysql';
    public const string DB_DRIVER_POSTGRES = 'pgsql';
    public const string DB_DRIVER_SQLITE = 'sqlite';
    public const string OP_INSERT = 'INSERT';
    public const string OP_UPDATE = 'UPDATE';
    public const string OP_DELETE = 'DELETE';

    // =========================================================================
    // 5. SECURITY & AUTHENTICATION ROLES
    // =========================================================================
    public const string ROLE_ADMIN = 'admin';
    public const string ROLE_MANAGER = 'manager';
    public const string ROLE_USER = 'user';
    public const string ROLE_GUEST = 'guest';
    public const array SYSTEM_ROLES = [self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_USER, self::ROLE_GUEST];

    // =========================================================================
    // 6. CRYPTOGRAPHY & CIPHER SPECS
    // =========================================================================
    public const string HASH_ARGON2ID = PASSWORD_ARGON2ID;
    public const string HASH_BCRYPT = PASSWORD_BCRYPT;
    public const string CIPHER_AES_256 = 'aes-256-gcm';

    // =========================================================================
    // 7. COMMON MIME TYPES FOR VALIDATION
    // =========================================================================
    public const string MIME_JSON = 'application/json';
    public const string MIME_XML = 'application/xml';
    public const string MIME_HTML = 'text/html';
    public const string MIME_PNG = 'image/png';
    public const string MIME_JPG = 'image/jpeg';
    public const string MIME_PDF = 'application/pdf';

    // =========================================================================
    // 8. DATA PERSISTENCE & FILE PATH TARGETS
    // =========================================================================
    public const string DIR_LOGS = 'storage/logs';
    public const string DIR_CACHE = 'storage/framework/cache';
    public const string DIR_UPLOADS = 'public/uploads';

    // =========================================================================
    // 9. LOG LEVEL IDENTIFIERS
    // =========================================================================
    public const string LOG_DEBUG = 'DEBUG';
    public const string LOG_INFO = 'INFO';
    public const string LOG_WARNING = 'WARNING';
    public const string LOG_ERROR = 'ERROR';
    public const string LOG_CRITICAL = 'CRITICAL';

    // =========================================================================
    // 10. SUCCESS TRANSACTION FEEDBACK MESSAGES
    // =========================================================================
    public const string MSG_SUCCESS_CREATE = 'Record successfully established in the storage system.';
    public const string MSG_SUCCESS_UPDATE = 'Database updates successfully processed.';
    public const string MSG_SUCCESS_DELETE = 'Target asset permanently extracted and wiped.';

    // =========================================================================
    // 11. SECURITY & SYSTEM FAILURE ALERTS
    // =========================================================================
    public const string MSG_ERR_AUTH = 'Access Denied. Security signatures do not match.';
    public const string MSG_ERR_VALIDATION = 'Data rules threshold checking failed validation.';
    public const string MSG_ERR_CRITICAL = 'Engine fault. Check system logs for full diagnostics tracking.';

    // =========================================================================
    // 12. RUNTIME LOOKUPS & MAP ENGINES
    // =========================================================================

    /**
     * Map HTTP numerical error flags to standard structural text titles instantly.
     */
    public static function getHttpStatusTitle(int $statusCode): string
    {
        return match ($statusCode) {
            self::HTTP_OK                  => 'OK',
            self::HTTP_CREATED             => 'Created',
            self::HTTP_NO_CONTENT          => 'No Content',
            self::HTTP_BAD_REQUEST         => 'Bad Request Payload',
            self::HTTP_UNAUTHORIZED        => 'Unauthorized Token Signature',
            self::HTTP_FORBIDDEN           => 'Forbidden Path Boundary',
            self::HTTP_NOT_FOUND           => 'Resource Location Dropped',
            self::HTTP_VALIDATION_FAILED   => 'Validation Integrity Failure',
            self::HTTP_INTERNAL_SERVER_ERROR => 'Fatal Stack Breakdown Code',
            default                        => 'Unknown Context Error Code Status'
        };
    }

    /**
     * 13. Dynamic message lookup block mapping keywords to operational string vectors.
     */
    public static function getFeedbackMessage(string $eventKey, string $fallback = ''): string
    {
        return match ($eventKey) {
            'create'     => self::MSG_SUCCESS_CREATE,
            'update'     => self::MSG_SUCCESS_UPDATE,
            'delete'     => self::MSG_SUCCESS_DELETE,
            'auth'       => self::MSG_ERR_AUTH,
            'validate'   => self::MSG_ERR_VALIDATION,
            'critical'   => self::MSG_ERR_CRITICAL,
            default      => $fallback
        };
    }

    /**
     * 14. Performance Metric: Returns an active registry list of all allowable system DB drivers.
     */
    public static function getSupportedDrivers(): array
    {
        return [self::DB_DRIVER_MYSQL, self::DB_DRIVER_POSTGRES, self::DB_DRIVER_SQLITE];
    }

    /**
     * 15. Check whether a given environment context configuration value runs as a production system.
     */
    public static function isProduction(string $currentEnvironment): bool
    {
        return strtolower($currentEnvironment) === self::ENV_PRODUCTION;
    }
}
