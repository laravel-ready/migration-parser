<?php

namespace LaravelReady\MigrationParser\Supports;

use PhpParser\Node\Name;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\StaticCall;
use LaravelReady\MigrationParser\Supports\TableParser;

class SchemaParser
{
    private ?object $baseExpression = null;

    public bool $isTableNameStatic = false;
    public bool $isTableCreateExpression = false;
    public ?string $tableName = null;
    public ?string $initialExpression = null;
    public ?string $blueprintVariableName = null;
    public mixed $tableContent = null;

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
                    $tableParser = new TableParser();

                    $this->tableContent = $tableParser->parse($this->baseExpression->args[1]->value->stmts, $this->blueprintVariableName);
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
}
