<?php

namespace LaravelReady\MigrationParser\Supports;

use Illuminate\Support\Str;

class FieldParser
{
    /**
     * Parse the table field
     * 
     * @param array $queryItems
     */
    public function parse(array $queryItems): mixed
    {
        $query = '';

        foreach ($queryItems as $key => $queryItem) {
            $currentItemArrayKey = $queryItem['key'];
            $currentItemArrayValue = $queryItem['value'];

            $nextItemArrayKey = $queryItems[$key + 1]['key'] ?? null;
            $nextItemArrayValue = $queryItems[$key + 1]['value'] ?? null;

            $next2ItemArrayKey = $queryItems[$key + 2]['key'] ?? null;
            $next2ItemArrayValue = $queryItems[$key + 2]['value'] ?? null;

            $next3ItemArrayKey = $queryItems[$key + 3]['key'] ?? null;
            $next3ItemArrayValue = $queryItems[$key + 3]['value'] ?? null;

            if ($currentItemArrayKey == 'nodeType') {
                if ($currentItemArrayValue == 'Expr_Variable' && $nextItemArrayKey == 'name') {
                    $query .= "\${$nextItemArrayValue}->";
                } else if ($currentItemArrayValue == 'Identifier' && $nextItemArrayKey == 'name') {
                    $query .= "{$nextItemArrayValue}(";

                    if ($nextItemArrayKey === 'name' && $nextItemArrayValue === 'default') {
                        $query .= "{$next3ItemArrayValue}";
                    }

                    if (!($next2ItemArrayKey == 'nodeType' && Str::startsWith($next2ItemArrayValue, 'Scalar_'))) {
                        $query .= ')->';
                    }
                } else if (Str::startsWith($currentItemArrayValue, 'Scalar_')) {
                    if (Str::endsWith($query, '\'') || !Str::endsWith($query, '(') || !Str::endsWith($query, ')') || !Str::endsWith($query, '->')) {
                        $query .= ', ';
                    }

                    $query .= "{$nextItemArrayValue}";

                    if ($next2ItemArrayValue == 'Identifier' && $next3ItemArrayKey == 'name') {
                        $query .= ')->';
                    }
                }
            }

            if ($key == count($queryItems) - 1) {
                if ($currentItemArrayKey === 'rawValue' || $currentItemArrayKey === '0') {
                    $query .= ');';
                } else if ($currentItemArrayKey === 'name') {
                    $query =  rtrim($query, '->') . ');';
                }
            }
        }

        $query = Str::replace("(, '", "('", $query);
        $query = Str::replace("(, \"", "(\"", $query);
        $query = Str::replace("));", ");", $query);

        if (Str::endsWith($query, '->')) {
            $query = rtrim($query, '->') . ';';
        }

        return $query;
    }
}
