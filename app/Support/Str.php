<?php
declare(strict_types=1);

namespace App\Support;

final class Str
{
    /** "Hello World!" -> "hello-world" */
    public static function slug(string $value, string $sep = '-'): string
    {
        $value = preg_replace('/[^\pL\pN]+/u', $sep, $value) ?? '';
        $value = trim($value, $sep);
        return mb_strtolower($value);
    }

    /** Cut text to N characters and add "..." */
    public static function limit(string $value, int $length = 100, string $end = '...'): string
    {
        return mb_strlen($value) <= $length ? $value : rtrim(mb_substr($value, 0, $length)) . $end;
    }

    /** "user_name" / "user-name" -> "userName" */
    public static function camel(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        return lcfirst(str_replace(' ', '', ucwords($value)));
    }

    /** "userName" / "user name" -> "user_name" */
    public static function snake(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;
        return mb_strtolower(preg_replace('/[\s\-]+/', '_', $value) ?? $value);
    }

    /** Cryptographically secure random string (tokens, codes) */
    public static function random(int $length = 16): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_contains($haystack, $needle);
    }

    /** "ravi kumar" -> "RK" */
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        return mb_strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2))));
    }
}
