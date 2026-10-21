<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * SOLID Design: Single Responsibility Principle (SRP) for explicit array mutations.
 * Optimization: Monostate implementation with strict type safety & early-exit native calls.
 */
final class ArrayHelper
{
    /**
     * Instantiation is blocked to enforce pure utility architecture.
     */
    private function __construct() {}

    /**
     * 1. Retrieve a nested value from an array using dot-notation.
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (!str_contains($key, '.')) {
            return $array[$key] ?? $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * 2. Check if a nested key exists inside an array using dot-notation.
     */
    public static function has(array $array, string $key): bool
    {
        if (!str_contains($key, '.')) {
            return array_key_exists($key, $array);
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }

        return true;
    }

    /**
     * 3. Set a nested value inside an array using dot-notation.
     */
    public static function set(array &$array, string $key, mixed $value): array
    {
        $keys = explode('.', $key);
        
        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
        return $array;
    }

    /**
     * 4. Return an array containing only the specified keys.
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * 5. Return an array excluding all the specified keys.
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * 6. PHP 8.4 Optimized: Find the FIRST value satisfying a custom callback.
     * Returns null if no match is found without exhausting memory allocations.
     */
    public static function find(array $array, callable $callback): mixed
    {
        return array_find($array, $callback);
    }

    /**
     * 7. PHP 8.4 Optimized: Find the FIRST key satisfying a custom callback.
     */
    public static function findKey(array $array, callable $callback): mixed
    {
        return array_find_key($array, $callback);
    }

    /**
     * 8. PHP 8.4 Optimized: True if AT LEAST ONE element satisfies the callback.
     */
    public static function any(array $array, callable $callback): bool
    {
        return array_any($array, $callback);
    }

    /**
     * 9. PHP 8.4 Optimized: True ONLY IF ALL elements satisfy the callback.
     */
    public static function all(array $array, callable $callback): bool
    {
        return array_all($array, $callback);
    }

    /**
     * 10. Flattens a multi-dimensional array into a single level dot-notation array.
     */
    public static function flatten(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, self::flatten($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    /**
     * 11. Extract a single column/property sequence from a multidimensional payload.
     */
    public static function pluck(array $array, string|int $value, string|int|null $key = null): array
    {
        return array_column($array, $value, $key);
    }

    /**
     * 12. Check whether an array behaves strictly as an associative map.
     */
    public static function isAssoc(array $array): bool
    {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * 13. Wraps a value into an array sequence if it isn't already an array.
     */
    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        return is_array($value) ? $value : [$value];
    }

    /**
     * 14. Group elements of an array cleanly based on a key identifier.
     */
    public static function groupBy(array $array, string|int $key): array
    {
        $result = [];
        foreach ($array as $item) {
            $groupKey = is_object($item) ? ($item->{$key} ?? null) : ($item[$key] ?? null);
            if ($groupKey !== null) {
                $result[$groupKey][] = $item;
            }
        }
        return $result;
    }

    /**
     * 15. Recursively filter out empty, null, or blank array elements.
     */
    public static function filterRecursive(array $array): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = self::filterRecursive($value);
            }
        }
        return array_filter($array, fn($val) => $val !== null && $val !== '' && $val !== []);
    }
}
