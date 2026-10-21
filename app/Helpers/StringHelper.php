<?php

declare(strict_types=1);

namespace app\Support;

/**
 * SOLID Design: Single Responsibility Principle (SRP) for multi-byte safe string transformations.
 * Optimization: Monostate immutable implementation with zero object overhead and high-speed native operations.
 */
final class StringHelper
{
    /**
     * Instantiation is blocked to enforce pure utility architecture.
     */
    private function __construct() {}

    /**
     * 1. Check if a string contains a specific substring.
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    /**
     * 2. Check if a string starts with a specific substring.
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    /**
     * 3. Check if a string ends with a specific substring.
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }

    /**
     * 4. PHP 8.4 Optimized: Capitalize the first character of a string safely using multi-byte support.
     */
    public static function ucfirst(string $string, string $encoding = 'UTF-8'): string
    {
        return mb_ucfirst($string, $encoding);
    }

    /**
     * 5. PHP 8.4 Optimized: Lowercase the first character of a string safely using multi-byte support.
     */
    public static function lcfirst(string $string, string $encoding = 'UTF-8'): string
    {
        return mb_lcfirst($string, $encoding);
    }

    /**
     * 6. Convert a string into a clean Title Case format.
     */
    public static function title(string $string, string $encoding = 'UTF-8'): string
    {
        return mb_convert_case($string, MB_CASE_TITLE, $encoding);
    }

    /**
     * 7. Safe multi-byte string length check.
     */
    public static function length(string $string, string $encoding = 'UTF-8'): int
    {
        return mb_strlen($string, $encoding);
    }

    /**
     * 8. Truncate text cleanly to a maximum length and append a suffix (e.g., for blogs or previews).
     */
    public static function limit(string $string, int $limit = 100, string $end = '...', string $encoding = 'UTF-8'): string
    {
        if (self::length($string, $encoding) <= $limit) {
            return $string;
        }

        return mb_substr($string, 0, $limit, $encoding) . $end;
    }

    /**
     * 9. Convert text into a URL-friendly slug (e.g., "Hello World!" becomes "hello-world").
     */
    public static function slug(string $string, string $separator = '-'): string
    {
        // Transliterate non-ascii characters to ascii equivalents
        $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        
        // Remove everything except alphanumeric symbols and spaces
        $string = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
        
        // Lowercase and swap spaces for separators
        return preg_replace('/\s+/', $separator, trim(mb_strtolower($string, 'UTF-8')));
    }

    /**
     * 10. Generate a cryptographically secure, random alphanumeric string.
     */
    public static function random(int $length = 16): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * 11. Convert a string to camelCase (e.g., "user_profile_data" -> "userProfileData").
     */
    public static function camel(string $value): string
    {
        $value = self::title(str_replace(['-', '_'], ' ', $value));
        return self::lcfirst(str_replace(' ', '', $value));
    }

    /**
     * 12. Convert a string to snake_case (e.g., "userProfileData" -> "user_profile_data").
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $value = preg_replace('/\s+/u', '', $value);
        $value = preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value);
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * 13. Mask a portion of a string (e.g., hiding credit card or email segments).
     */
    public static function mask(string $string, string $character, int $index, ?int $length = null, string $encoding = 'UTF-8'): string
    {
        if ($character === '') {
            return $string;
        }

        $segmentLength = self::length($string, $encoding);

        if ($index < 0) {
            $index = max(0, $segmentLength + $index);
        }

        if ($length === null) {
            $length = $segmentLength - $index;
        } elseif ($length < 0) {
            $length = max(0, $segmentLength + $length - $index);
        }

        if ($length === 0) {
            return $string;
        }

        $start = mb_substr($string, 0, $index, $encoding);
        $mask = str_repeat($character, $length);
        $end = mb_substr($string, $index + $length, $segmentLength, $encoding);

        return $start . $mask . $end;
    }

    /**
     * 14. Safely wrap a string value within another string boundaries.
     */
    public static function wrap(string $value, string $before, ?string $after = null): string
    {
        return $before . $value . ($after ?? $before);
    }

    /**
     * 15. Extract everything found after the first occurrence of a specific value.
     */
    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }
        $parts = explode($search, $subject, 2);
        return array_key_exists(1, $parts) ? $parts[1] : $subject;
    }
}
