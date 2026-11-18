<?php

declare(strict_types=1);

namespace Core;

final class Env
{
    /** @var array<string, string> */
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            self::parseLine($line);
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        // Real environment (Docker) wins over .env file (XAMPP)
        $real = getenv($key);
        if ($real !== false && $real !== '') {
            return $real;
        }

        $value = self::$vars[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    private static function parseLine(string $rawLine): void
    {
        $line = trim($rawLine);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            return;
        }

        [$rawKey, $rawValue] = explode('=', $line, 2);
        $key = trim($rawKey);
        if ($key === '') {
            return;
        }

        self::$vars[$key] = self::normalizeValue(trim($rawValue));
    }

    private static function normalizeValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $firstChar = $value[0];
        if (($firstChar === '"' || $firstChar === "'") && \strlen($value) >= 2 && str_ends_with($value, $firstChar)) {
            return substr($value, 1, -1);
        }

        return trim(explode(' #', $value, 2)[0]);
    }
}
