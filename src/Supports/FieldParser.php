<?php

namespace LaravelReady\MigrationParser\Supports;

use Illuminate\Support\Str;
use LaravelReady\MigrationParser\Enums\CommentFlag;
use LaravelReady\MigrationParser\Helpers\CommonHelpers;

class FieldParser
{
    public ?string $queryString = null;
    public array $queryFields = [];
    private array $queryItems = [];

    /**
     * @param array $queryItems
     */
    public function __construct(array $queryItems)
    {
        $this->queryItems = $queryItems;

        $this->stringifyQuery();
    }

    /**
     * Parse the table field
     *
     * @return FieldParser
     */
    public function parse(): self
    {
        $currentFieldIndex = null;

        foreach ($this->queryItems as $key => $queryItem) {
            $currentItemArrayKey = $queryItem['key'];
            $currentItemArrayValue = $queryItem['value'];

            $nextItemArrayKey = $this->queryItems[$key + 1]['key'] ?? null;
            $nextItemArrayValue = $this->queryItems[$key + 1]['value'] ?? null;

            $next2ItemArrayKey = $this->queryItems[$key + 2]['key'] ?? null;
            $next2ItemArrayValue = $this->queryItems[$key + 2]['value'] ?? null;

            // fields
            if ($currentItemArrayKey == 'nodeType') {
                if ($currentItemArrayValue == 'Identifier' && $nextItemArrayKey == 'name') {
                    if ($nextItemArrayValue !== 'comment') {
                        $this->queryFields[$key] = [
                            'field' => $nextItemArrayValue,
                            'values' => [],
                        ];

                        $currentFieldIndex = $key;
                    }
                } else if (Str::startsWith($currentItemArrayValue, 'Scalar_')) {
                    // parameter values and type castings
                    if (Str::startsWith($nextItemArrayValue, "'")) {
                        $this->queryFields[$currentFieldIndex]['values'][] = trim($nextItemArrayValue, "'");
                    } else if (is_numeric($nextItemArrayValue)) {
                        $this->queryFields[$currentFieldIndex]['values'][] = (int)$nextItemArrayValue;
                    } else if ($nextItemArrayValue === 'true' || $nextItemArrayValue === 'false') {
                        $this->queryFields[$currentFieldIndex]['values'][] = $nextItemArrayValue === 'true';
                    } else if (Str::startsWith($nextItemArrayValue, 'null')) {
                        $this->queryFields[$currentFieldIndex]['values'][] = null;
                    }
                }
            } else if ($currentItemArrayKey === 'name' && $currentItemArrayValue === 'comment') {
                // comment
                if (Str::startsWith($nextItemArrayValue, 'Scalar_String') && $next2ItemArrayKey === 'rawValue') {
                    $commentData = $this->parseFlags($next2ItemArrayValue);

                    $this->queryFields[$key] = [
                        'field' => 'comment',
                        'values' => [$next2ItemArrayValue],
                        'flags' => $commentData ?? [],
                    ];
                }
            }

            if ($key == count($this->queryItems) - 1 && $currentItemArrayKey === 0) {
                if (Str::startsWith($currentItemArrayValue, "'")) {
                    $this->queryFields[$currentFieldIndex]['values'][] = trim($currentItemArrayValue, "'");
                } else if (is_numeric($currentItemArrayValue)) {
                    $this->queryFields[$currentFieldIndex]['values'][] = (int)$currentItemArrayValue;
                } else if ($currentItemArrayValue === 'true' || $currentItemArrayValue === 'false') {
                    $this->queryFields[$currentFieldIndex]['values'][] = $currentItemArrayValue === 'true';
                } else if (Str::startsWith($currentItemArrayValue, 'null')) {
                    $this->queryFields[$currentFieldIndex]['values'][] = null;
                }
            }
        }

        $this->queryFields = array_values($this->queryFields);

        return $this;
    }

    /**
     * Stringify the query
     */
    protected function stringifyQuery(): void
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

    /**
     * Parse the flags
     *
     * @param string $comment
     *
     * @return array
     */
    protected function parseFlags(string $comment): array
    {
        $flags = [];

        if (empty($comment)) {
            return $flags;
        }

        if (Str::contains($comment, 'f:')) {
            preg_match_all(pattern: '/f::([A-Z \d\W]+)/', subject: $comment, matches: $matches);

            if ($matches && count($matches) > 1) {
                foreach ($matches as $match) {
                    $flagString = $match[0] ?? null;

                    if (!empty($flagString)) {
                        $flagString = Str::replace('f::', '', $flagString);

                        $flagMatches = array_map('trim', explode(',', $flagString));

                        foreach ($flagMatches as $flagMatch) {
                            if (Str::contains($flagMatch, '=')) {
                                $flagMatchPair = explode('=', $flagMatch);
                                $flagKey = trim($flagMatchPair[0], "' \t\n\r\0\x0B");

                                $flags[] = [
                                    'key' => CommentFlag::tryFrom($flagKey),
                                    'value' => trim($flagMatchPair[1], "' \t\n\r\0\x0B"),
                                ];
                            } else {
                                $flagKey = trim($flagMatch, "' \t\n\r\0\x0B");

                                $flags[] = [
                                    'key' => CommentFlag::tryFrom($flagKey),
                                    'value' => null,
                                ];
                            }
                        }
                    }
                }
            }
        }

        $flags = array_filter($flags, fn ($item) => $item['key'] !== null);

        return CommonHelpers::multidimensionalUnique($flags);
    }
}
