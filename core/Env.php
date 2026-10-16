<?php
declare(strict_types=1);

namespace Core;

final class Env
{
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $value = trim($value, "\"'");
            } else {
                $value = trim(explode(' #', $value, 2)[0]);
            }
            self::$vars[trim($key)] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        // Real environment (Docker) wins over .env file (XAMPP)
        $real = getenv($key);
        if ($real !== false && $real !== '') {
            return $real;
        }
        $v = self::$vars[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }
}
