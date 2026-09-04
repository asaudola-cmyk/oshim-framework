<?php
declare(strict_types=1);

namespace Oshim\Database\Migrations;

use Oshim\Database\Connection;
use Oshim\Database\ConnectionManager;
use RuntimeException;

class Migrator
{
    public function __construct(
        protected MigrationRepository $repository,
        protected Connection $connection
    ) {
    }

    public function run(array|string $paths = []): array
    {
        $this->repository->createRepository();

        $files = $this->getMigrationFiles($paths);
        $ran = $this->repository->getRan();

        $pending = array_diff($files, $ran);
        if (empty($pending)) {
            return [];
        }

        $batch = $this->repository->getNextBatchNumber();
        $executed = [];

        foreach ($pending as $file) {
            $migration = $this->resolve($file, $paths);
            $migration->up();

            $this->repository->log($file, $batch);
            $executed[] = $file;
        }

        return $executed;
    }

    public function rollback(array|string $paths = [], int $steps = 1): array
    {
        $this->repository->createRepository();

        $rolledBack = [];

        for ($i = 0; $i < $steps; $i++) {
            $lastMigrations = $this->repository->getLast();
            if (empty($lastMigrations)) {
                break;
            }

            foreach ($lastMigrations as $record) {
                $file = $record['migration'];
                $migration = $this->resolve($file, $paths);
                $migration->down();

                $this->repository->delete($file);
                $rolledBack[] = $file;
            }
        }

        return $rolledBack;
    }

    public function reset(array|string $paths = []): array
    {
        $all = [];
        while (!empty($rolledBack = $this->rollback($paths, 1))) {
            $all = array_merge($all, $rolledBack);
        }
        return $all;
    }

    public function status(array|string $paths = []): array
    {
        $this->repository->createRepository();

        $files = $this->getMigrationFiles($paths);
        $ran = $this->repository->getRan();

        $status = [];
        foreach ($files as $file) {
            $status[] = [
                'migration' => $file,
                'ran'       => in_array($file, $ran, true),
            ];
        }

        return $status;
    }

    public function getMigrationFiles(array|string $paths = []): array
    {
        $paths = (array)(empty($paths) ? [defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH . '/database/migrations' : dirname(__DIR__, 3) . '/database/migrations'] : $paths);

        $files = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $glob = glob(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '*.php');
            if ($glob !== false) {
                foreach ($glob as $filePath) {
                    $files[] = basename($filePath, '.php');
                }
            }
        }

        sort($files);
        return $files;
    }

    protected function resolve(string $file, array|string $paths = []): Migration
    {
        $paths = (array)(empty($paths) ? [defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH . '/database/migrations' : dirname(__DIR__, 3) . '/database/migrations'] : $paths);

        $filePath = null;
        foreach ($paths as $dir) {
            $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file . '.php';
            if (is_file($candidate)) {
                $filePath = $candidate;
                break;
            }
        }

        if ($filePath === null) {
            throw new RuntimeException("Migration file [{$file}.php] not found.");
        }

        $migration = require $filePath;

        // Support anonymous migration class: return new class extends Migration { ... };
        if ($migration instanceof Migration) {
            return $migration;
        }

        // Support named class matching migration filename
        $className = $this->getClassNameFromFile($file);
        if (class_exists($className)) {
            $instance = new $className();
            if ($instance instanceof Migration) {
                return $instance;
            }
        }

        throw new RuntimeException("Migration [{$file}] must return an instance of Migration.");
    }

    protected function getClassNameFromFile(string $file): string
    {
        // Remove timestamp prefix (e.g. 2026_08_29_000001_create_users_table)
        $clean = preg_replace('/^[0-9_]+/', '', $file);
        $words = str_replace('_', ' ', (string)$clean);
        return str_replace(' ', '', ucwords($words));
    }
}
