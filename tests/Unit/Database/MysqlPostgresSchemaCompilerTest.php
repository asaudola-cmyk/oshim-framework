<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use Oshim\Testing\TestCase;
use Oshim\Database\Schema\Blueprint;
use Oshim\Database\Schema\ColumnDefinition;
use Oshim\Database\Schema\ForeignKeyDefinition;
use Oshim\Database\Schema\Compilers\MysqlSchemaCompiler;
use Oshim\Database\Schema\Compilers\PostgresSchemaCompiler;
use Oshim\Database\Schema\Compilers\SqliteSchemaCompiler;
use Oshim\Database\Schema\Compilers\SchemaCompilerInterface;
use Oshim\Database\Drivers\MysqlDriver;
use Oshim\Database\Drivers\PostgresDriver;
use Oshim\Database\Drivers\SqliteDriver;

class MysqlPostgresSchemaCompilerTest extends TestCase
{
    public function testMysqlCompileCreateGeneratesValidDdl(): void
    {
        $compiler = new MysqlSchemaCompiler();
        $blueprint = new Blueprint('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('email')->unique();
            $table->boolean('is_active')->default(true);
            $table->float('score');
            $table->double('latitude');
            $table->decimal('balance', 14, 4)->default('0.0000');
            $table->json('preferences')->nullable();
            $table->uuid('uuid');
            $table->string('nickname')->comment('User nickname')->after('name')->nullable();
            $table->timestamps();
        });

        $statements = $compiler->compileCreate($blueprint);
        $this->assertCount(1, $statements);

        $sql = $statements[0];
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('`id` INT UNSIGNED AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('`name` VARCHAR(100) NOT NULL', $sql);
        $this->assertStringContainsString('`email` VARCHAR(255) NOT NULL', $sql);
        $this->assertStringContainsString('`is_active` TINYINT(1) NOT NULL DEFAULT 1', $sql);
        $this->assertStringContainsString('`score` FLOAT NOT NULL', $sql);
        $this->assertStringContainsString('`latitude` DOUBLE NOT NULL', $sql);
        $this->assertStringContainsString('`balance` DECIMAL(14, 4) NOT NULL DEFAULT 0.0000', $sql);
        $this->assertStringContainsString('`preferences` JSON NULL', $sql);
        $this->assertStringContainsString('`uuid` CHAR(36) NOT NULL', $sql);
        $this->assertStringContainsString('`nickname` VARCHAR(255) NULL COMMENT \'User nickname\' AFTER `name`', $sql);
        $this->assertStringContainsString('`created_at` DATETIME NULL', $sql);
        $this->assertStringContainsString('`updated_at` DATETIME NULL', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
        $this->assertStringContainsString('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $sql);
    }

    public function testMysqlCompileCreateWithForeignKeysAndCompositeKeys(): void
    {
        $compiler = new MysqlSchemaCompiler();
        $blueprint = new Blueprint('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('order_number');
            $table->primary(['id', 'order_number']);
            $table->unique(['user_id', 'order_number'], 'uniq_user_order');
            $table->index(['created_at', 'status'], 'idx_orders_created_status');
            $table->foreign('user_id', 'fk_orders_user')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete()
                  ->onUpdate('NO ACTION');
            $table->foreign(['user_id', 'order_number'])
                  ->references(['id', 'number'])
                  ->on('legacy_orders')
                  ->nullOnDelete();
        });

        $statements = $compiler->compileCreate($blueprint);
        $this->assertCount(1, $statements);
        $sql = $statements[0];

        $this->assertStringContainsString('PRIMARY KEY (`id`, `order_number`)', $sql);
        $this->assertStringContainsString('UNIQUE KEY `uniq_user_order` (`user_id`, `order_number`)', $sql);
        $this->assertStringContainsString('KEY `idx_orders_created_status` (`created_at`, `status`)', $sql);
        $this->assertStringContainsString('CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION', $sql);
        $this->assertStringContainsString('FOREIGN KEY (`user_id`, `order_number`) REFERENCES `legacy_orders` (`id`, `number`) ON DELETE SET NULL', $sql);
    }

    public function testMysqlCompileTableAlter(): void
    {
        $compiler = new MysqlSchemaCompiler();
        $blueprint = new Blueprint('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable();
            $table->string('email', 191)->nullable()->change();
            $table->dropColumn('temporary_token');
            $table->dropColumn(['legacy_col1', 'legacy_col2']);
            $table->index('phone', 'idx_users_phone');
            $table->unique('email', 'uniq_users_email');
            $table->dropIndex('idx_old');
            $table->dropUnique('uniq_old');
            $table->foreign('profile_id', 'fk_users_profile')->references('id')->on('profiles')->onDelete('CASCADE');
            $table->dropForeign('fk_users_old');
            $table->primary(['id', 'tenant_id']);
            $table->dropPrimary();
        });

        $statements = $compiler->compileTable($blueprint);

        $this->assertContains('ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(20) NULL', $statements);
        $this->assertContains('ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(191) NULL', $statements);
        $this->assertContains('ALTER TABLE `users` DROP COLUMN `temporary_token`', $statements);
        $this->assertContains('ALTER TABLE `users` DROP COLUMN `legacy_col1`', $statements);
        $this->assertContains('ALTER TABLE `users` DROP COLUMN `legacy_col2`', $statements);
        $this->assertContains('ALTER TABLE `users` ADD INDEX `idx_users_phone` (`phone`)', $statements);
        $this->assertContains('ALTER TABLE `users` ADD UNIQUE KEY `uniq_users_email` (`email`)', $statements);
        $this->assertContains('ALTER TABLE `users` DROP INDEX `idx_old`', $statements);
        $this->assertContains('ALTER TABLE `users` DROP INDEX `uniq_old`', $statements);
        $this->assertContains('ALTER TABLE `users` ADD CONSTRAINT `fk_users_profile` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE', $statements);
        $this->assertContains('ALTER TABLE `users` DROP FOREIGN KEY `fk_users_old`', $statements);
        $this->assertContains('ALTER TABLE `users` ADD PRIMARY KEY (`id`, `tenant_id`)', $statements);
        $this->assertContains('ALTER TABLE `users` DROP PRIMARY KEY', $statements);
    }

    public function testMysqlDropRenameAndInspectionStatements(): void
    {
        $compiler = new MysqlSchemaCompiler();

        $this->assertSame('DROP TABLE `accounts`', $compiler->compileDrop('accounts'));
        $this->assertSame('DROP TABLE IF EXISTS `accounts`', $compiler->compileDropIfExists('accounts'));
        $this->assertSame('RENAME TABLE `old_accounts` TO `new_accounts`', $compiler->compileRename('old_accounts', 'new_accounts'));
        $this->assertSame("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'accounts'", $compiler->compileTableExists('accounts'));
        
        $allColsSql = $compiler->compileColumnExists('accounts', '');
        $this->assertSame("SELECT COLUMN_NAME AS name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'accounts'", $allColsSql);

        $oneColSql = $compiler->compileColumnExists('accounts', 'balance');
        $this->assertSame("SELECT COLUMN_NAME AS name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'accounts' AND COLUMN_NAME = 'balance'", $oneColSql);
    }

    public function testPostgresCompileCreateGeneratesValidDdl(): void
    {
        $compiler = new PostgresSchemaCompiler();
        $blueprint = new Blueprint('servers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('hostname', 150);
            $table->integer('vcpus')->default(4);
            $table->float('load_avg');
            $table->double('memory_usage');
            $table->decimal('monthly_cost', 12, 3)->default('19.990');
            $table->json('specs')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('cluster_id');
            $table->index(['hostname', 'cluster_id'], 'idx_servers_host_cluster');
            $table->unique('hostname', 'uniq_servers_hostname');
            $table->timestamps();
        });

        $statements = $compiler->compileCreate($blueprint);
        $this->assertTrue(count($statements) >= 3);

        $createTableSql = $statements[0];
        $this->assertStringContainsString('CREATE TABLE "servers"', $createTableSql);
        $this->assertStringContainsString('"id" BIGSERIAL', $createTableSql);
        $this->assertStringContainsString('"hostname" VARCHAR(150) NOT NULL', $createTableSql);
        $this->assertStringContainsString('"vcpus" INTEGER NOT NULL DEFAULT 4', $createTableSql);
        $this->assertStringContainsString('"load_avg" REAL NOT NULL', $createTableSql);
        $this->assertStringContainsString('"memory_usage" DOUBLE PRECISION NOT NULL', $createTableSql);
        $this->assertStringContainsString('"monthly_cost" NUMERIC(12, 3) NOT NULL DEFAULT 19.990', $createTableSql);
        $this->assertStringContainsString('"specs" JSONB NULL', $createTableSql);
        $this->assertStringContainsString('"is_active" BOOLEAN NOT NULL DEFAULT TRUE', $createTableSql);
        $this->assertStringContainsString('"cluster_id" UUID NOT NULL', $createTableSql);
        $this->assertStringContainsString('"created_at" TIMESTAMP WITHOUT TIME ZONE NULL', $createTableSql);
        $this->assertStringContainsString('PRIMARY KEY ("id")', $createTableSql);

        $this->assertContains('CREATE INDEX IF NOT EXISTS "idx_servers_host_cluster" ON "servers" ("hostname", "cluster_id")', $statements);
        $this->assertContains('CREATE UNIQUE INDEX IF NOT EXISTS "uniq_servers_hostname" ON "servers" ("hostname")', $statements);
    }

    public function testPostgresCompileCreateWithForeignKeysAndCompositeKeys(): void
    {
        $compiler = new PostgresSchemaCompiler();
        $blueprint = new Blueprint('invoices', function (Blueprint $table) {
            $table->bigInteger('id');
            $table->string('series');
            $table->unsignedBigInteger('customer_id');
            $table->primary(['id', 'series']);
            $table->foreign('customer_id', 'fk_invoices_customer')
                  ->references('id')
                  ->on('customers')
                  ->cascadeOnDelete()
                  ->restrictOnDelete();
            $table->foreign(['customer_id', 'series'])
                  ->references(['id', 'series'])
                  ->on('legacy_customers')
                  ->nullOnDelete();
        });

        $statements = $compiler->compileCreate($blueprint);
        $createTableSql = $statements[0];

        $this->assertStringContainsString('PRIMARY KEY ("id", "series")', $createTableSql);
        $this->assertStringContainsString('CONSTRAINT "fk_invoices_customer" FOREIGN KEY ("customer_id") REFERENCES "customers" ("id") ON DELETE RESTRICT', $createTableSql);
        $this->assertStringContainsString('FOREIGN KEY ("customer_id", "series") REFERENCES "legacy_customers" ("id", "series") ON DELETE SET NULL', $createTableSql);
    }

    public function testPostgresCompileTableAlter(): void
    {
        $compiler = new PostgresSchemaCompiler();
        $blueprint = new Blueprint('nodes', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable();
            $table->integer('port')->default(8080)->change();
            $table->dropColumn('old_hash');
            $table->dropColumn(['deprecated_a', 'deprecated_b']);
            $table->index('ip_address', 'idx_nodes_ip');
            $table->unique('ip_address', 'uniq_nodes_ip');
            $table->dropIndex('idx_nodes_ip');
            $table->dropUnique('uniq_nodes_ip');
            $table->foreign('cluster_id', 'fk_nodes_cluster')->references('id')->on('clusters')->onDelete('CASCADE');
            $table->dropForeign('fk_nodes_cluster');
            $table->primary(['id', 'region']);
            $table->dropPrimary('nodes_pkey');
        });

        $statements = $compiler->compileTable($blueprint);

        $this->assertContains('ALTER TABLE "nodes" ADD COLUMN "ip_address" VARCHAR(45) NULL', $statements);
        $this->assertContains('ALTER TABLE "nodes" ALTER COLUMN "port" TYPE INTEGER', $statements);
        $this->assertContains('ALTER TABLE "nodes" ALTER COLUMN "port" SET NOT NULL', $statements);
        $this->assertContains('ALTER TABLE "nodes" ALTER COLUMN "port" SET DEFAULT 8080', $statements);
        $this->assertContains('ALTER TABLE "nodes" DROP COLUMN IF EXISTS "old_hash"', $statements);
        $this->assertContains('ALTER TABLE "nodes" DROP COLUMN IF EXISTS "deprecated_a"', $statements);
        $this->assertContains('ALTER TABLE "nodes" DROP COLUMN IF EXISTS "deprecated_b"', $statements);
        $this->assertContains('CREATE INDEX IF NOT EXISTS "idx_nodes_ip" ON "nodes" ("ip_address")', $statements);
        $this->assertContains('CREATE UNIQUE INDEX IF NOT EXISTS "uniq_nodes_ip" ON "nodes" ("ip_address")', $statements);
        $this->assertContains('DROP INDEX IF EXISTS "idx_nodes_ip"', $statements);
        $this->assertContains('ALTER TABLE "nodes" DROP CONSTRAINT IF EXISTS "uniq_nodes_ip"', $statements);
        $this->assertContains('ALTER TABLE "nodes" ADD CONSTRAINT "fk_nodes_cluster" FOREIGN KEY ("cluster_id") REFERENCES "clusters" ("id") ON DELETE CASCADE', $statements);
        $this->assertContains('ALTER TABLE "nodes" DROP CONSTRAINT IF EXISTS "fk_nodes_cluster"', $statements);
        $this->assertContains('ALTER TABLE "nodes" ADD PRIMARY KEY ("id", "region")', $statements);
        $this->assertContains('ALTER TABLE "nodes" DROP CONSTRAINT IF EXISTS "nodes_pkey"', $statements);
    }

    public function testPostgresDropRenameAndInspectionStatements(): void
    {
        $compiler = new PostgresSchemaCompiler();

        $this->assertSame('DROP TABLE "records"', $compiler->compileDrop('records'));
        $this->assertSame('DROP TABLE IF EXISTS "records"', $compiler->compileDropIfExists('records'));
        $this->assertSame('ALTER TABLE "old_records" RENAME TO "new_records"', $compiler->compileRename('old_records', 'new_records'));
        $this->assertSame("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename = 'records'", $compiler->compileTableExists('records'));

        $allColsSql = $compiler->compileColumnExists('records', '');
        $this->assertSame("SELECT column_name AS name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'records'", $allColsSql);

        $oneColSql = $compiler->compileColumnExists('records', 'data');
        $this->assertSame("SELECT column_name AS name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'records' AND column_name = 'data'", $oneColSql);
    }

    public function testDriverSchemaCompilerWiring(): void
    {
        $mysqlDriver = new MysqlDriver();
        $this->assertInstanceOf(SchemaCompilerInterface::class, $mysqlDriver->getSchemaCompiler());
        $this->assertInstanceOf(MysqlSchemaCompiler::class, $mysqlDriver->getSchemaCompiler());

        $postgresDriver = new PostgresDriver();
        $this->assertInstanceOf(SchemaCompilerInterface::class, $postgresDriver->getSchemaCompiler());
        $this->assertInstanceOf(PostgresSchemaCompiler::class, $postgresDriver->getSchemaCompiler());

        $sqliteDriver = new SqliteDriver();
        $this->assertInstanceOf(SchemaCompilerInterface::class, $sqliteDriver->getSchemaCompiler());
        $this->assertInstanceOf(SqliteSchemaCompiler::class, $sqliteDriver->getSchemaCompiler());
    }

    public function testColumnAndForeignKeyDefinitionFluentBuilders(): void
    {
        $col = new ColumnDefinition('meta', 'string', ['precision' => 10, 'scale' => 2]);
        $col->comment('metadata column')
            ->after('id')
            ->change()
            ->nullable()
            ->unsigned()
            ->default('test')
            ->unique();

        $this->assertSame('metadata column', $col->comment);
        $this->assertSame('id', $col->after);
        $this->assertTrue($col->change);
        $this->assertTrue($col->nullable);
        $this->assertTrue($col->unsigned);
        $this->assertSame('test', $col->default);
        $this->assertTrue($col->unique);

        $fk = new ForeignKeyDefinition('user_id');
        $fk->references('id')
           ->on('users')
           ->name('fk_custom_user')
           ->cascadeOnDelete()
           ->onUpdate('RESTRICT');

        $this->assertSame(['user_id'], $fk->columns);
        $this->assertSame(['id'], $fk->foreignColumns);
        $this->assertSame('users', $fk->foreignTable);
        $this->assertSame('fk_custom_user', $fk->name);
        $this->assertSame('CASCADE', $fk->onDelete);
        $this->assertSame('RESTRICT', $fk->onUpdate);
    }
}
