<?php

namespace LaravelReady\MigrationParser\Supports;

use Illuminate\Support\Str;

class FieldParser
{
    public ?string $queryString = null;
    private array $queryItems = [];

    /**
     * @param array $queryItems
     */
    public function __construct(array $queryItems)
    {
        $this->queryItems = $queryItems;

        $this->stringfyQuery();
    }

    /**
     * Parse the table field
     */
    public function parse(): self
    {
        return $this;
    }

    /**
     * Stringfy the query
     */
    public function stringfyQuery(): void
    {
        $query = '';

        foreach ($this->queryItems as $key => $queryItem) {
            $currentItemArrayKey = $queryItem['key'];
            $currentItemArrayValue = $queryItem['value'];

            $nextItemArrayKey = $this->queryItems[$key + 1]['key'] ?? null;
            $nextItemArrayValue = $this->queryItems[$key + 1]['value'] ?? null;

            $next2ItemArrayKey = $this->queryItems[$key + 2]['key'] ?? null;
            $next2ItemArrayValue = $this->queryItems[$key + 2]['value'] ?? null;

            $next3ItemArrayKey = $this->queryItems[$key + 3]['key'] ?? null;
            $next3ItemArrayValue = $this->queryItems[$key + 3]['value'] ?? null;

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

            if ($key == count($this->queryItems) - 1) {
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

        $this->queryString = $query;
    }
}
