<?php

namespace LaravelReady\MigrationParser\Supports;

use PhpParser\Node;
use PhpParser\Error;
use ReflectionClass;
use PhpParser\Parser;
use ReflectionMethod;
use PhpParser\Node\Arg;
use PhpParser\Node\Name;
use Illuminate\Support\Str;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\ClassMethod;
use Illuminate\Support\Facades\Config;
use LaravelReady\MigrationParser\Helpers\CommonHelpers;
use LaravelReady\MigrationParser\Exceptions\PhpParseException;

class SchemaParser
{
    private ?object $baseExpression = null;

    public bool $isTableNameStatic = false;
    public bool $isTableCreateExpression = false;
    public ?string $tableName = null;
    public ?string $initialExpression = null;
    public ?string $blueprintVariableName = null;
    public array $expressionList = [];

    public function __construct(object $schemeExpressions)
    {
        $this->baseExpression = $schemeExpressions->expr ?? null;
    }

    /**
     * Parse schema code and extract required information.
     *
     * $this->baseExpression->expr->args[0] is the first argument of the Schema::method(xxx, ...) context.
     * $this->baseExpression->expr->args[1] is the second argument of the Schema::method(..., xxx) context.
     */
    public function parse(): self
    {
        // check table name
        if (isset($this->baseExpression->args[0]->value)) {
            // some table names are can be dynamic, so we need to check if it is static or not
            // like this: Schema::create(Config::get('package-alias.config_name' . 'table_name'), function (Blueprint $table) {
            $this->isTableNameStatic = $this->baseExpression->args[0]->value instanceof String_;

            // ignore dynamic table names, we can not parse them for now
            if ($this->isTableNameStatic) {
                $this->tableName = $this->baseExpression->args[0]->value->value;

                $this->parseSchemaExpression();
            }
        }

        // check blueprint variable
        if (isset($this->baseExpression->args[1]->value) && $this->baseExpression->args[1]->value instanceof Closure) {
            $params = $this->baseExpression->args[1]->value->params;

            if (count($params) > 0 && $params[0]->type instanceof Name && $params[0]->type->toString() === 'Blueprint') {
                // get blueprint variable name
                $this->blueprintVariableName = $params[0]->var instanceof Variable ? $params[0]->var->name : null;

                if ($this->blueprintVariableName) {
                    $this->parseTable($this->baseExpression->args[1]->value->stmts, $this->blueprintVariableName);
                }
            }
        }

        return $this;
    }

    /**
     * Parse the initial schema expression
     *
     * Example: "Schema::create('table_name', function (Blueprint $table)..." return "create"
     */
    private function parseSchemaExpression(): void
    {
        // check if this is a create table expression
        if ($this->baseExpression instanceof StaticCall) {
            if (
                $this->baseExpression?->class instanceof Name
                && $this->baseExpression?->class?->parts[0] === 'Schema'
            ) {
                $this->initialExpression = $this->baseExpression?->name?->name;

                $this->isTableCreateExpression = $this->initialExpression === 'create';
            }
        }
    }

    /**
     * Parse the table query
     * 
     * @param array $tableFields
     * @param string $blueprintVariableName
     */
    private function parseTable(array $tableFields, string $blueprintVariableName)
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

                $this->expressionList[$key] = $this->parseField($queryItems);
            }
        }
    }

    /**
     * Parse the table field
     * 
     * @param array $queryItems
     */
    private function parseField(array $queryItems): mixed
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
