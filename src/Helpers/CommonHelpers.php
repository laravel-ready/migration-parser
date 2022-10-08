<?php

namespace LaravelReady\MigrationParser\Helpers;

use Exception;

class CommonHelpers extends Exception
{
    /**
     * Flatten a multi-dimensional array
     * 
     * @param array $array
     * 
     * @return array
     */
    public static function arrayFlatten(array $array): array
    {
        $return = [];

        array_walk_recursive($array, function ($a, $b) use (&$return) {
            $return[] = [$b => $a];
        });

        return $return;
    }
}
