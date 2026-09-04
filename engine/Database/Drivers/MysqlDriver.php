<?php
declare(strict_types=1);

namespace Oshim\Database\Drivers;

use Oshim\Database\Query\Compilers\CompilerInterface;
use Oshim\Database\Query\Compilers\MysqlCompiler;
use Oshim\Database\Schema\Compilers\SchemaCompilerInterface;
use Oshim\Database\Schema\Compilers\MysqlSchemaCompiler;
use PDO;

class MysqlDriver implements DriverInterface
{
    protected ?CompilerInterface $compiler = null;
    protected ?SchemaCompilerInterface $schemaCompiler = null;

    public function getName(): string
    {
        return 'mysql';
    }

    public function connect(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true, // ADVANCED OPTIMIZATION: Persistent Connections
        ];

        return new PDO($dsn, $username, $password, $options);
    }

    public function getCompiler(): CompilerInterface
    {
        return $this->compiler ??= new MysqlCompiler();
    }

    public function getSchemaCompiler(): SchemaCompilerInterface
    {
        return $this->schemaCompiler ??= new MysqlSchemaCompiler();
    }
}
