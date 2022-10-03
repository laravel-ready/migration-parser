<?php

namespace LaravelReady\MigrationParser\Supports;

use PhpParser\Node;
use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Arg;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\ClassMethod;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use LaravelReady\MigrationParser\Exceptions\PhpParseException;

class MigrationFileParser
{
    private Parser $parser;
    private Filesystem $file;
    private string $basePath;
    public Return_|Class_|null $migrationClass = null;

    public function __construct()
    {
        $this->file = new Filesystem();
        $this->basePath = Config::get('migration-parser.migrations_folder');

        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    }

    /**
     * Get the migration class name from the migration file.
     * This method only returns first class name in the file.
     * 
     * @param string $migrationFilePath
     * @return self
     */
    public function parse(string $migrationFilePath): self
    {
        $fullFilePath = "{$this->basePath}/{$migrationFilePath}";

        if ($this->file->exists($fullFilePath)) {
            $fileContents = $this->file->get($fullFilePath);

            try {
                $parsedItems = $this->parser->parse($fileContents);

                // parse and extract migration class
                $parsedClasses = $parsedItems && is_array($parsedItems)
                    ? array_values(
                        array_filter($parsedItems, fn ($item) => $item instanceof Class_ || $item instanceof Return_)
                    )
                    : null;

                // get single class
                if ($parsedClasses) {
                    $this->migrationClass = is_array($parsedClasses) && isset($parsedClasses[0])
                        ? $parsedClasses[0]
                        : $parsedClasses;
                }
            } catch (Error $error) {
                throw new PhpParseException("Parse error: {$error->getMessage()}\n");
            }
        }

        return $this;
    }

    /**
     * Get the migration class name from the migration file.
     * 
     * @return string|null
     */
    public function getClassName(): ?string
    {
        // get migration class name
        if ($this->migrationClass) {
            if ($this->migrationClass instanceof Class_) {
                return $this->migrationClass->name->toString();
            } else if ($this->migrationClass instanceof Return_) {
                return '_AnonymousMigrationClass_';
            }
        }

        return null;
    }

    /**
     * Return migration class is anonymous or not.
     * 
     * @return bool
     */
    public function isAnonymousClass(): bool
    {
        return $this->migrationClass && $this->migrationClass instanceof Return_;
    }

    public function getUpMethod(): ClassMethod|null
    {
        $methods = $this->getMethods();

        if ($methods) {
            return array_filter($methods, fn ($method) => $method->name->toString() === 'up')[0] ?? null;
        }

        return null;
    }

    public function getDownMethod(): ClassMethod|null
    {
        $methods = $this->getMethods();

        if ($methods) {
            return array_filter($methods, fn ($method) => $method->name->toString() === 'down')[0] ?? null;
        }

        return null;
    }

    public function getSchemas(): mixed
    {
        $upMethod = $this->getUpMethod();

        if ($upMethod) {
            // get only schema builder and create calls
            $schemeExpressions = $upMethod?->stmts;

            $schemas = array_map(fn ($item) => (new SchemaParser($item))->parse(), $schemeExpressions);
            $schemas = array_filter($schemas, fn ($item) => $item && $item->tableName !== null);

            return $schemas;
        }

        return null;
    }

    /**
     * Get the migration class methods.
     * 
     * @return array|null
     */
    private function getMethods(): ?array
    {
        $methods = [];

        if ($this->migrationClass) {
            if ($this->migrationClass instanceof Class_) {
                $methods = $this->migrationClass->stmts;
            } else if ($this->migrationClass instanceof Return_) {
                $methods = $this->migrationClass?->expr?->class?->stmts;
            }

            $methods = array_values(
                array_filter($methods, fn ($item) => $item instanceof ClassMethod)
            );
        }

        return $methods && count($methods) ? $methods : null;
    }
}
