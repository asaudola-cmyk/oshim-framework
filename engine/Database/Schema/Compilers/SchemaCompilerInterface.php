<?php
declare(strict_types=1);

namespace Oshim\Database\Schema\Compilers;

use Oshim\Database\Schema\Blueprint;

interface SchemaCompilerInterface
{
    /**
     * Compile table creation Blueprint into list of DDL SQL statements.
     *
     * @return list<string>
     */
    public function compileCreate(Blueprint $blueprint): array;

    /**
     * Compile table modification Blueprint into list of DDL SQL statements.
     *
     * @return list<string>
     */
    public function compileTable(Blueprint $blueprint): array;

    public function compileDrop(string $table): string;
    public function compileDropIfExists(string $table): string;
    public function compileRename(string $from, string $to): string;
    public function compileTableExists(string $table): string;
    public function compileColumnExists(string $table, string $column): string;
}
