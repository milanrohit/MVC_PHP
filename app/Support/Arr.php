<?php
declare(strict_types=1);

namespace App\Support;

final class Arr
{
    /** Arr::get($data, 'user.address.city', 'N/A') */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }

    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /** Arr::pluck($users, 'email') -> ['a@x.com', 'b@x.com'] */
    public static function pluck(array $rows, string $key): array
    {
        return array_map(fn ($row) => is_array($row) ? ($row[$key] ?? null) : ($row->$key ?? null), $rows);
    }

    /** Group rows by a key value */
    public static function groupBy(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[is_array($row) ? $row[$key] : $row->$key][] = $row;
        }
        return $out;
    }

    public static function first(array $array, mixed $default = null): mixed
    {
        return $array === [] ? $default : reset($array);
    }
}
