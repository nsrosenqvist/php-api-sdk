<?php

namespace NSRosenqvist\ApiToolkit;

use stdClass;

/**
 * Perform a recursive array map
 * @see https://stackoverflow.com/a/39637749
 *
 * @param [type] $callback
 * @param [type] $array
 * @return array
 */
function array_map_recursive($callback, $array): array
{
    $func = function ($item) use (&$func, &$callback) {
        return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
    };

    return array_map($func, $array);
}

/**
 * Cast a multi-dimensional array into an object
 * @see https://gist.github.com/machitgarha/e47ce6580cd0964e8a71cf8eb1e52644
 *
 * @param array $array
 * @return object
 */
function to_object(array $array): object
{
    $object = new stdClass();

    foreach ($array as $key => $value) {
        if (strlen($key)) {
            if (is_array($value) && is_assoc($value)) {
                $object->{$key} = to_object($value);
            } else {
                $object->{$key} = $value;
            }
        }
    }

    return $object;
}

/**
 * Determine if the array is associative or not
 * @see https://www.php.net/manual/en/function.is-array.php#84488
 *
 * @param array $array
 * @return boolean
 */
function is_assoc(array $array): bool
{
    foreach (array_keys($array) as $key => $value) {
        if ($key !== $value) {
            return true;
        }
    }

    return false;
}
