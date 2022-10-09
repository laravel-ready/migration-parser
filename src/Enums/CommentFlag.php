<?php

namespace LaravelReady\MigrationParser\Enums;

use LaravelReady\MigrationParser\Traits\EnumTrait;

enum CommentFlag : string
{
    use EnumTrait;

    case IGNORE = 'ignore';
}
