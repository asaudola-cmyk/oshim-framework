<?php
declare(strict_types=1);

namespace Oshim\Database\Migrations;

use Oshim\Database\Connection;
use Oshim\Database\ConnectionManager;

abstract class Migration
{
    protected ?string $connection = null;

    abstract public function up(): void;
    abstract public function down(): void;

    public function getConnection(): Connection
    {
        return ConnectionManager::getInstance()->connection($this->connection);
    }
}
