<?php

namespace LaravelReady\MigrationParser\Traits;

use ReflectionEnum;
use LaravelReady\MigrationParser\Exceptions\EnumException;

/**
 * Trait EnumTrait
 * 
 * @source https://stackoverflow.com/a/72645216/6940144
 */
trait EnumTrait
{
    public static function tryFrom(string $caseName): ?self
    {
        $reflectionEnum = new ReflectionEnum(self::class);

        return $reflectionEnum->hasCase($caseName) ? $reflectionEnum->getConstant($caseName) : null;
    }

    public static function from(string $caseName): self
    {
        return self::tryFrom($caseName) ?? throw new EnumException("Enum {$caseName} not found in " . self::class);
    }
}
