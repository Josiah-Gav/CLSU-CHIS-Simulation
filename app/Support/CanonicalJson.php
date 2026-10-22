<?php

namespace App\Support;

/**
 * Hashes a decoded JSON body independently of key order, so a retry that
 * serialises the same object with its keys in a different order still counts
 * as identical content. List order is significant and is kept.
 */
class CanonicalJson
{
    public static function hash(array $data): string
    {
        return hash('sha256', json_encode(
            self::sortKeys($data),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        ));
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::sortKeys(...), $value);
    }
}
