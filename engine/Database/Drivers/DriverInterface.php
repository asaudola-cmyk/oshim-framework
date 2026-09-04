<?php
declare(strict_types=1);

namespace Oshim\Database\Drivers;

use Oshim\Database\Query\Compilers\CompilerInterface;
use Oshim\Database\Schema\Compilers\SchemaCompilerInterface;
use PDO;

interface DriverInterface
{
    public function connect(array $config): PDO;
    public function getName(): string;
    public function getCompiler(): CompilerInterface;
    public function getSchemaCompiler(): SchemaCompilerInterface;
}
