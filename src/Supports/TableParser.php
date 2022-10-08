<?php

namespace LaravelReady\MigrationParser\Supports;

use LaravelReady\MigrationParser\Helpers\CommonHelpers;

class TableParser
{
    public array $expressionList = [];

    /**
     * Parse the table query
     * 
     * @param array $tableFields
     * @param string $blueprintVariableName
     */
    public function parse(array $tableFields, string $blueprintVariableName)
    {
        if ($blueprintVariableName && $tableFields && is_array($tableFields) && count($tableFields)) {
            $excludedDataKeys = [
                'startLine',
                'endLine',
                'line',
                'endFilePos',
                'startFilePos',
                'filePos',
                'kind',
                'byRef',
                'unpack',
                'text',
            ];

            foreach ($tableFields as $key => $tableField) {
                $arrayFieldTree = json_decode(json_encode($tableField->expr), true);
                $flattenArrayTree = CommonHelpers::arrayFlatten($arrayFieldTree);

                // convert to key value pairs
                $queryItems = array_map(function ($item) {
                    return [
                        'key' => array_keys($item)[0],
                        'value' => array_values($item)[0],
                    ];
                }, $flattenArrayTree);

                // filter out the data we don't need
                $queryItems = array_filter($queryItems, function ($item) use ($excludedDataKeys) {
                    return is_array($item) // array required
                        && !in_array($item['key'], $excludedDataKeys) // exclude some keys
                        && !empty($item['value']); // exclude empty values
                }, ARRAY_FILTER_USE_BOTH);

                // reset array keys
                $queryItems = array_values($queryItems);

                // again, filter out the data we don't need
                $queryItems = array_filter(
                    $queryItems,
                    function ($item, $i) use ($queryItems) {
                        $nextItemArrayKey = $queryItems[$i + 1]['key'] ?? null;
                        $previousItemArrayKey = $queryItems[$i - 1]['key'] ?? null;

                        return $item['key'] !== $nextItemArrayKey // exclude sequential keys
                            && !($item['key'] === 'value' && $previousItemArrayKey === 'rawValue'); // keep only rawValue
                    },
                    ARRAY_FILTER_USE_BOTH
                );

                // reset array keys
                $queryItems = array_values($queryItems);

                $fieldParser = new FieldParser();

                $this->expressionList[$key] = $fieldParser->parse($queryItems);
            }

            return $this;
        }
    }
}
