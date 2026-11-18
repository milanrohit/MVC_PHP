<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

/**
 * Static, multi-byte safe string helpers.
 *
 * Requires PHP 8.4+ (mb_ucfirst, mb_lcfirst) and the mbstring and iconv extensions.
 */
final class StringHelper
{
    private function __construct()
    {
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return \str_contains($haystack, $needle);
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        return \str_starts_with($haystack, $needle);
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        return \str_ends_with($haystack, $needle);
    }

    public static function ucfirst(string $string, string $encoding = 'UTF-8'): string
    {
        return \mb_ucfirst($string, $encoding);
    }

    public static function lcfirst(string $string, string $encoding = 'UTF-8'): string
    {
        return \mb_lcfirst($string, $encoding);
    }

    /**
     * Title Case ("hello wORLD" -> "Hello World"). Lower-cases the rest of each word.
     */
    public static function title(string $string, string $encoding = 'UTF-8'): string
    {
        return \mb_convert_case($string, MB_CASE_TITLE, $encoding);
    }

    public static function length(string $string, string $encoding = 'UTF-8'): int
    {
        return \mb_strlen($string, $encoding);
    }

    /**
     * Truncates to $limit characters and appends $end (the suffix is not counted in the limit).
     */
    public static function limit(
        string $string,
        int $limit = 100,
        string $end = '...',
        string $encoding = 'UTF-8',
    ): string {
        $limit = \max(0, $limit);

        if (self::length($string, $encoding) <= $limit) {
            return $string;
        }

        return \mb_substr($string, 0, $limit, $encoding) . $end;
    }

    /**
     * URL-friendly slug ("Hello, World!" -> "hello-world", "Café Ñandú" -> "cafe-nandu").
     * Runs of anything other than letters and digits (including "-" and "_") become one separator.
     * Transliteration uses iconv; on musl-based images (Alpine) accents may not transliterate.
     */
    public static function slug(string $string, string $separator = '-'): string
    {
        $ascii = \iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        $words = \preg_split('/[^a-z0-9]+/', \strtolower($ascii === false ? $string : $ascii), -1, PREG_SPLIT_NO_EMPTY);

        return \implode($separator, $words === false ? [] : $words);
    }

    /**
     * Cryptographically secure random hexadecimal string of exactly $length characters.
     */
    public static function random(int $length = 16): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Length must be at least 1.');
        }

        return \substr(\bin2hex(\random_bytes(\intdiv($length + 1, 2))), 0, $length);
    }

    /**
     * "user_profile_data" -> "userProfileData". Existing capitals inside words are kept.
     */
    public static function camel(string $value): string
    {
        $words = \preg_split('/[\s_-]+/u', $value, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            throw new InvalidArgumentException('Input is not valid UTF-8.');
        }

        return self::lcfirst(\implode('', \array_map(self::ucfirst(...), $words)));
    }

    /**
     * "userProfileData" or "user profile data" -> "user_profile_data".
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $value = self::regexReplace('/\s+/u', '', \ucwords($value));
        $value = self::regexReplace('/(.)(?=\p{Lu})/u', '$1' . \addcslashes($delimiter, '\\$'), $value);

        return \mb_strtolower($value, 'UTF-8');
    }

    /**
     * Masks part of a string, e.g. mask('4111111111111111', '*', 0, 12) -> '************1111'.
     * A negative $index counts from the end; a negative $length stops that many characters before the end.
     */
    public static function mask(
        string $string,
        string $character,
        int $index,
        ?int $length = null,
        string $encoding = 'UTF-8',
    ): string {
        if ($character === '') {
            return $string;
        }

        $total = self::length($string, $encoding);
        $index = $index < 0 ? \max(0, $total + $index) : \min($index, $total);

        $length = match (true) {
            $length === null => $total - $index,
            $length < 0 => \max(0, $total + $length - $index),
            default => \min($length, $total - $index),
        };

        if ($length === 0) {
            return $string;
        }

        return \mb_substr($string, 0, $index, $encoding)
            . \str_repeat($character, $length)
            . \mb_substr($string, $index + $length, null, $encoding);
    }

    public static function wrap(string $value, string $before, ?string $after = null): string
    {
        return $before . $value . ($after ?? $before);
    }

    /**
     * Everything after the first occurrence of $search; the whole string if it is not found.
     */
    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = \strpos($subject, $search);

        return $position === false ? $subject : \substr($subject, $position + \strlen($search));
    }

    private static function regexReplace(string $pattern, string $replacement, string $subject): string
    {
        $result = \preg_replace($pattern, $replacement, $subject);

        if ($result === null) {
            throw new InvalidArgumentException('Input is not valid UTF-8.');
        }

        return $result;
    }
}
