<?php

namespace YassineDabbous\DynamicQuery;

class DynamicQueryHelper
{
    /**
     * Convert simple array to associative.
     */
    public static function toAssociative(array $array): array
    {
        $res = [];
        foreach ($array as $k => $v) {
            if (is_numeric($k)) {
                if (is_string($v) || is_int($v)) {
                    $res[$v] = null;
                }
                // Silently skip non-scalar values (arrays, objects)
            } else {
                $res[$k] = $v;
            }
        }
        return $res;
    }

    /**
     * Normalize array so all values are arrays.
     */
    public static function normalizeAssociativeArray(array $array): array
    {
        $array = static::toAssociative($array);
        foreach ($array as $k => $v) {
            if (is_null($v)) {
                $array[$k] = [];
            } elseif (!is_array($v)) {
                $array[$k] = [$v];
            }
        }
        return $array;
    }

    /**
     * Sanitize SQL alias.
     */
    public static function sanitizeAlias(string $alias): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $alias);
    }

    /**
     * Resolve recursive dependencies.
     */
    public static function recursiveDependencies(array $associative, array $keys): array
    {
        $seen = [];
        $maxIterations = 50;
        $iterations = 0;
        $more = true;

        $requested = array_intersect(array_keys($associative), $keys);

        while ($more) {
            if (++$iterations > $maxIterations) {
                break;
            }
            $more = false;
            $filtered = array_intersect_key($associative, array_flip($requested));

            foreach ($filtered as $deps) {
                if (!$deps) {
                    continue;
                }
                foreach ((array) $deps as $dep) {
                    if (isset($seen[$dep])) {
                        continue;
                    }
                    $seen[$dep] = true;
                    $keys[] = $dep;
                    $requested[] = $dep;
                    $more = true;
                }
            }
        }

        return array_unique($keys);
    }
}
