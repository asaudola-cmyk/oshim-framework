<?php
declare(strict_types=1);

namespace Oshim\Database\Drivers;

use Oshim\Database\Query\Compilers\CompilerInterface;
use Oshim\Database\Query\Compilers\PostgresCompiler;
use Oshim\Database\Schema\Compilers\SchemaCompilerInterface;
use Oshim\Database\Schema\Compilers\PostgresSchemaCompiler;
use PDO;

class PostgresDriver implements DriverInterface
{
    protected ?CompilerInterface $compiler = null;
    protected ?SchemaCompilerInterface $schemaCompiler = null;

    public function getName(): string
    {
        return 'pgsql';
    }

    public function connect(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 5432;
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? 'postgres';
        $password = $config['password'] ?? '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$database};options='--client_encoding=UTF8'";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true,
        ];

        return new PDO($dsn, $username, $password, $options);
    }

    public function getCompiler(): CompilerInterface
    {
        return $this->compiler ??= new PostgresCompiler();
    }

    public function getSchemaCompiler(): SchemaCompilerInterface
    {
        return $this->schemaCompiler ??= new PostgresSchemaCompiler();
    }
}
