<?php

namespace LaravelReady\MigrationParser\Services;

class MigrationParserService
{
    public function __construct()
    {
    }

    /**
     * This is nonstatic service method
     *
     * @return string
     */
    public function myServiceFunction(string $input): string
    {
        return $input;
    }
}
