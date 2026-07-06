<?php

namespace Illuminate\Tests\Integration\Database\Sqlite;

use Illuminate\Tests\Integration\Database\SchemaForeignKeyIndexTest as BaseSchemaForeignKeyIndexTest;
use Orchestra\Testbench\Attributes\RequiresDatabase;

#[RequiresDatabase('sqlite')]
class SchemaForeignKeyIndexTest extends BaseSchemaForeignKeyIndexTest
{
    //
}
