<?php
declare(strict_types=1);

namespace Oshim\Database\Migrations;

use Oshim\Database\Connection;
use Oshim\Database\Schema\Schema;
use Oshim\Database\Schema\Blueprint;

class MigrationRepository
{
    protected string $table = '_migrations';

    public function __construct(protected Connection $connection)
    {
    }

    public function createRepository(): void
    {
        if ($this->repositoryExists()) {
            return;
        }

        Schema::connection($this->connection->getName())->create($this->table, function (Blueprint $table) {
            $table->id();
            $table->string('migration', 255);
            $table->integer('batch');
            $table->timestamp('applied_at');
        });
    }

    public function repositoryExists(): bool
    {
        return Schema::connection($this->connection->getName())->hasTable($this->table);
    }

    public function getRan(): array
    {
        if (!$this->repositoryExists()) {
            return [];
        }

        return $this->connection->table($this->table)
            ->orderBy('batch', 'asc')
            ->orderBy('id', 'asc')
            ->pluck('migration');
    }

    public function getLast(): array
    {
        if (!$this->repositoryExists()) {
            return [];
        }

        $lastBatch = $this->getLastBatchNumber();

        return $this->connection->table($this->table)
            ->where('batch', '=', $lastBatch)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getLastBatchNumber(): int
    {
        if (!$this->repositoryExists()) {
            return 0;
        }

        return (int)$this->connection->table($this->table)->max('batch') ?: 0;
    }

    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    public function log(string $file, int $batch): void
    {
        $this->connection->table($this->table)->insert([
            'migration'  => $file,
            'batch'      => $batch,
            'applied_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $file): void
    {
        $this->connection->table($this->table)->where('migration', '=', $file)->delete();
    }
}
