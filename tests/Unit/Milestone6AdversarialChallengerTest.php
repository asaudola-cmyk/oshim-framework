<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Database\Schema\Blueprint;
use Oshim\Database\Schema\ColumnDefinition;
use Oshim\Database\Schema\ForeignKeyDefinition;
use Oshim\Database\Schema\Compilers\MysqlSchemaCompiler;
use Oshim\Database\Schema\Compilers\PostgresSchemaCompiler;
use Oshim\Database\Schema\Compilers\SqliteSchemaCompiler;
use Oshim\Ai\Inference\OshimLlmEngine;
use Oshim\Ai\Inference\Providers\LlmProviderInterface;
use Oshim\Ai\Inference\Providers\OpenAiProvider;
use Oshim\Ai\Inference\Providers\AnthropicProvider;
use Oshim\Ai\Inference\Providers\GeminiProvider;
use Oshim\Ai\Inference\Providers\OllamaProvider;
use Oshim\Ai\Inference\Providers\LocalGgufProvider;
use Oshim\Ai\Tokenizer\GgufTokenizer;
use Oshim\Ai\Tensor\MatrixMath;
use Oshim\Ai\Tensor\Tensor;
use Oshim\Cli\CliApplication;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Cli\Command;
use Oshim\Cli\Commands\ServeCommand;
use Oshim\Cli\Commands\TestCommand;
use Oshim\Cli\Commands\MigrateCommand;
use Oshim\Cli\Commands\RollbackCommand;
use Oshim\Cli\Commands\SeedCommand;
use Oshim\Cli\Commands\MakeModelCommand;
use Oshim\Cli\Commands\MakeMigrationCommand;
use Oshim\Cli\Commands\MakeControllerCommand;
use Oshim\Cli\Commands\MakeComponentCommand;
use Oshim\Cli\Commands\KeyGenerateCommand;
use Oshim\Cli\Commands\UniversalInfoCommand;
use Oshim\Cli\Commands\TurboServeCommand;
use Oshim\Cli\Commands\TurboBenchCommand;
use Oshim\Cli\Commands\MobileBuildCommand;
use Oshim\Cli\Commands\DesktopServeCommand;
use Oshim\Cli\Commands\AiChatCommand;
use Oshim\Cli\Commands\AiRagCommand;
use Oshim\Cli\Commands\AiTeamCommand;
use Oshim\Cli\Commands\PdfInvoiceCommand;
use Oshim\Cli\Commands\TotpQrCommand;
use Oshim\Cli\Commands\QueueWorkCommand;
use Oshim\Cli\Commands\CacheClearCommand;
use Oshim\Cli\Commands\AppCreateCommand;
use Oshim\Cli\Commands\AppBundleCommand;
use Oshim\Cli\Commands\AppRunCommand;
use Oshim\Cli\Commands\BillingCronCommand;
use Oshim\Cli\Commands\DnsServeCommand;
use Oshim\Cli\Commands\DnsStartCommand;
use Oshim\Cli\Commands\NodeStartCommand;
use Oshim\Cli\Commands\S3ServeCommand;
use Oshim\Cli\Commands\SslIssueCommand;
use Oshim\Cli\Commands\VmSpawnCommand;
use Oshim\Cli\Commands\ScheduleRunCommand;
use Oshim\Cli\Commands\MakeCrudCommand;
use RuntimeException;
use Exception;
use Error;

/**
 * 👑 Milestone 6 Adversarial System Stress Verification Test Suite
 * Empirical stress tests across:
 * 1. Database Schema Compilers (MySQL & PostgreSQL complex DDL, composite keys, altering columns, foreign keys)
 * 2. AI Multi-Provider routing and fallback handling under invalid providers or missing keys
 * 3. GGUF BPE Tokenizer with special tokens, multilingual text, byte fallback, and dense tensor embeddings
 * 4. CLI command execution with anomalous arguments and options
 */
final class Milestone6AdversarialChallengerTest extends TestCase
{
    // =========================================================================
    // SECTION 1: Database Schema Compilers Stress (MySQL & PostgreSQL)
    // =========================================================================

    public function testMysqlComplexDdlWithTripleCompositeKeysAndCustomConstraints(): void
    {
        $compiler = new MysqlSchemaCompiler();
        $blueprint = new Blueprint('cloud_tenant_resources', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('org_id');
            $table->string('resource_id', 64);
            $table->string('name', 128)->nullable();
            $table->bigInteger('quota_limit')->unsigned()->default(1000);
            $table->decimal('cost_rate', 10, 4)->default('0.0500');
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Composite Primary Key
            $table->primary(['tenant_id', 'org_id', 'resource_id']);

            // Composite Unique Key with explicit identifier
            $table->unique(['org_id', 'name'], 'uniq_org_res_name');

            // Composite Index
            $table->index(['tenant_id', 'is_active', 'created_at'], 'idx_tenant_active_created');

            // Complex Foreign Key
            $table->foreign(['tenant_id', 'org_id'], 'fk_res_tenant_org')
                  ->references(['id', 'organization_id'])
                  ->on('organizations')
                  ->onDelete('CASCADE')
                  ->onUpdate('RESTRICT');
        });

        $statements = $compiler->compileCreate($blueprint);
        $this->assertCount(1, $statements);
        $sql = $statements[0];

        $this->assertStringContainsString('CREATE TABLE `cloud_tenant_resources`', $sql);
        $this->assertStringContainsString('`tenant_id` CHAR(36) NOT NULL', $sql);
        $this->assertStringContainsString('`org_id` CHAR(36) NOT NULL', $sql);
        $this->assertStringContainsString('`resource_id` VARCHAR(64) NOT NULL', $sql);
        $this->assertStringContainsString('`quota_limit` BIGINT UNSIGNED NOT NULL DEFAULT 1000', $sql);
        $this->assertStringContainsString('`cost_rate` DECIMAL(10, 4) NOT NULL DEFAULT 0.0500', $sql);
        $this->assertStringContainsString('`is_active` TINYINT(1) NOT NULL DEFAULT 1', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`tenant_id`, `org_id`, `resource_id`)', $sql);
        $this->assertStringContainsString('UNIQUE KEY `uniq_org_res_name` (`org_id`, `name`)', $sql);
        $this->assertStringContainsString('KEY `idx_tenant_active_created` (`tenant_id`, `is_active`, `created_at`)', $sql);
        $this->assertStringContainsString('CONSTRAINT `fk_res_tenant_org` FOREIGN KEY (`tenant_id`, `org_id`) REFERENCES `organizations` (`id`, `organization_id`) ON DELETE CASCADE ON UPDATE RESTRICT', $sql);
    }

    public function testPostgresComplexDdlWithTripleCompositeKeysAndSeparateIndexes(): void
    {
        $compiler = new PostgresSchemaCompiler();
        $blueprint = new Blueprint('cloud_tenant_resources', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('org_id');
            $table->string('resource_id', 64);
            $table->string('name', 128)->nullable();
            $table->bigInteger('quota_limit')->unsigned()->default(1000);
            $table->decimal('cost_rate', 10, 4)->default('0.0500');
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Composite Primary Key
            $table->primary(['tenant_id', 'org_id', 'resource_id']);

            // Composite Unique Key with explicit identifier
            $table->unique(['org_id', 'name'], 'uniq_org_res_name');

            // Composite Index
            $table->index(['tenant_id', 'is_active', 'created_at'], 'idx_tenant_active_created');

            // Complex Foreign Key
            $table->foreign(['tenant_id', 'org_id'], 'fk_res_tenant_org')
                  ->references(['id', 'organization_id'])
                  ->on('organizations')
                  ->onDelete('CASCADE')
                  ->onUpdate('RESTRICT');
        });

        $statements = $compiler->compileCreate($blueprint);
        $this->assertTrue(count($statements) >= 3);

        $createSql = $statements[0];
        $this->assertStringContainsString('CREATE TABLE "cloud_tenant_resources"', $createSql);
        $this->assertStringContainsString('"tenant_id" UUID NOT NULL', $createSql);
        $this->assertStringContainsString('"org_id" UUID NOT NULL', $createSql);
        $this->assertStringContainsString('"resource_id" VARCHAR(64) NOT NULL', $createSql);
        $this->assertStringContainsString('"quota_limit" BIGINT NOT NULL DEFAULT 1000', $createSql);
        $this->assertStringContainsString('"cost_rate" NUMERIC(10, 4) NOT NULL DEFAULT 0.0500', $createSql);
        $this->assertStringContainsString('"is_active" BOOLEAN NOT NULL DEFAULT TRUE', $createSql);
        $this->assertStringContainsString('PRIMARY KEY ("tenant_id", "org_id", "resource_id")', $createSql);
        $this->assertStringContainsString('CONSTRAINT "fk_res_tenant_org" FOREIGN KEY ("tenant_id", "org_id") REFERENCES "organizations" ("id", "organization_id") ON DELETE CASCADE ON UPDATE RESTRICT', $createSql);

        $this->assertContains('CREATE UNIQUE INDEX IF NOT EXISTS "uniq_org_res_name" ON "cloud_tenant_resources" ("org_id", "name")', $statements);
        $this->assertContains('CREATE INDEX IF NOT EXISTS "idx_tenant_active_created" ON "cloud_tenant_resources" ("tenant_id", "is_active", "created_at")', $statements);
    }

    public function testSchemaCompilersAllDataTypeMappingsStress(): void
    {
        $mysql = new MysqlSchemaCompiler();
        $postgres = new PostgresSchemaCompiler();

        $blueprint = new Blueprint('type_matrix', function (Blueprint $t) {
            $t->increments('col_inc');
            $t->bigIncrements('col_biginc');
            $t->tinyInteger('col_tiny');
            $t->smallInteger('col_small');
            $t->addColumn('col_medium', 'mediumInteger');
            $t->integer('col_int');
            $t->bigInteger('col_bigint');
            $t->unsignedInteger('col_uint');
            $t->unsignedBigInteger('col_ubigint');
            $t->string('col_str', 120);
            $t->text('col_text');
            $t->mediumText('col_mtext');
            $t->longText('col_ltext');
            $t->boolean('col_bool');
            $t->float('col_float');
            $t->double('col_double');
            $t->decimal('col_dec', 8, 3);
            $t->date('col_date');
            $t->time('col_time');
            $t->dateTime('col_datetime');
            $t->timestamp('col_timestamp');
            $t->json('col_json');
            $t->addColumn('col_bin', 'binary');
            $t->uuid('col_uuid');
        });

        $mysqlSql = $mysql->compileCreate($blueprint)[0];
        $this->assertStringContainsString('`col_inc` INT UNSIGNED AUTO_INCREMENT', $mysqlSql);
        $this->assertStringContainsString('`col_biginc` BIGINT UNSIGNED AUTO_INCREMENT', $mysqlSql);
        $this->assertStringContainsString('`col_tiny` TINYINT NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_small` SMALLINT NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_medium` MEDIUMINT NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_int` INT NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_bigint` BIGINT NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_uint` INT UNSIGNED NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_ubigint` BIGINT UNSIGNED NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_str` VARCHAR(120) NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_text` TEXT NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_mtext` MEDIUMTEXT NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_ltext` LONGTEXT NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_bool` TINYINT(1) NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_float` FLOAT NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_double` DOUBLE NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_dec` DECIMAL(8, 3) NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_date` DATE NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_time` TIME NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_datetime` DATETIME NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_timestamp` DATETIME NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_json` JSON NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_bin` BLOB NOT NULL', $mysqlSql);
        $this->assertStringContainsString('`col_uuid` CHAR(36) NOT NULL', $mysqlSql);

        $pgSql = $postgres->compileCreate($blueprint)[0];
        $this->assertStringContainsString('"col_inc" SERIAL', $pgSql);
        $this->assertStringContainsString('"col_biginc" BIGSERIAL', $pgSql);
        $this->assertStringContainsString('"col_tiny" SMALLINT NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_small" SMALLINT NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_medium" INTEGER NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_int" INTEGER NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_bigint" BIGINT NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_uint" INTEGER NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_ubigint" BIGINT NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_str" VARCHAR(120) NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_text" TEXT NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_mtext" TEXT NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_ltext" TEXT NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_bool" BOOLEAN NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_float" REAL NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_double" DOUBLE PRECISION NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_dec" NUMERIC(8, 3) NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_date" DATE NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_time" TIME WITHOUT TIME ZONE NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_datetime" TIMESTAMP WITHOUT TIME ZONE NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_timestamp" TIMESTAMP WITHOUT TIME ZONE NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_json" JSONB NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_bin" BYTEA NOT NULL', $pgSql);
        $this->assertStringContainsString('"col_uuid" UUID NOT NULL', $pgSql);
    }

    public function testSchemaCompilersReservedWordsEscapingStress(): void
    {
        $mysql = new MysqlSchemaCompiler();
        $postgres = new PostgresSchemaCompiler();

        $blueprint = new Blueprint('order', function (Blueprint $t) {
            $t->id('select');
            $t->string('group');
            $t->string('where');
            $t->index('group', 'idx_group');
            $t->unique('where', 'uniq_where');
        });

        $mSql = $mysql->compileCreate($blueprint)[0];
        $this->assertStringContainsString('CREATE TABLE `order`', $mSql);
        $this->assertStringContainsString('`select` BIGINT UNSIGNED AUTO_INCREMENT', $mSql);
        $this->assertStringContainsString('`group` VARCHAR(255) NOT NULL', $mSql);
        $this->assertStringContainsString('`where` VARCHAR(255) NOT NULL', $mSql);

        $pStatements = $postgres->compileCreate($blueprint);
        $this->assertStringContainsString('CREATE TABLE "order"', $pStatements[0]);
        $this->assertStringContainsString('"select" BIGSERIAL', $pStatements[0]);
        $this->assertStringContainsString('"group" VARCHAR(255) NOT NULL', $pStatements[0]);
        $this->assertStringContainsString('"where" VARCHAR(255) NOT NULL', $pStatements[0]);
    }

    public function testAlteringColumnsAndDroppingForeignKeysStress(): void
    {
        $mysql = new MysqlSchemaCompiler();
        $postgres = new PostgresSchemaCompiler();

        $blueprint = new Blueprint('cluster_nodes', function (Blueprint $table) {
            // Modify existing columns
            $table->string('status', 50)->default('idle')->nullable()->change();
            $table->bigInteger('memory_bytes')->unsigned()->default(1073741824)->change();

            // Add new column
            $table->string('rack_unit', 32)->after('status')->nullable();

            // Drops
            $table->dropForeign('fk_nodes_cluster');
            $table->dropUnique('uniq_nodes_ip_port');
            $table->dropIndex('idx_nodes_region');
            $table->dropColumn(['deprecated_metric_a', 'deprecated_metric_b']);
        });

        $mStatements = $mysql->compileTable($blueprint);
        $this->assertContains('ALTER TABLE `cluster_nodes` MODIFY COLUMN `status` VARCHAR(50) NULL DEFAULT \'idle\'', $mStatements);
        $this->assertContains('ALTER TABLE `cluster_nodes` MODIFY COLUMN `memory_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 1073741824', $mStatements);
        $this->assertContains('ALTER TABLE `cluster_nodes` ADD COLUMN `rack_unit` VARCHAR(32) NULL AFTER `status`', $mStatements);
        $this->assertContains('ALTER TABLE `cluster_nodes` DROP FOREIGN KEY `fk_nodes_cluster`', $mStatements);
        $this->assertContains('ALTER TABLE `cluster_nodes` DROP INDEX `uniq_nodes_ip_port`', $mStatements);
        $this->assertContains('ALTER TABLE `cluster_nodes` DROP INDEX `idx_nodes_region`', $mStatements);
        $this->assertContains('ALTER TABLE `cluster_nodes` DROP COLUMN `deprecated_metric_a`', $mStatements);
        $this->assertContains('ALTER TABLE `cluster_nodes` DROP COLUMN `deprecated_metric_b`', $mStatements);

        $pStatements = $postgres->compileTable($blueprint);
        $this->assertContains('ALTER TABLE "cluster_nodes" ALTER COLUMN "status" TYPE VARCHAR(50)', $pStatements);
        $this->assertContains('ALTER TABLE "cluster_nodes" ALTER COLUMN "status" DROP NOT NULL', $pStatements);
        $this->assertContains('ALTER TABLE "cluster_nodes" ALTER COLUMN "status" SET DEFAULT \'idle\'', $pStatements);
        $this->assertContains('ALTER TABLE "cluster_nodes" ALTER COLUMN "memory_bytes" TYPE BIGINT', $pStatements);
        $this->assertContains('ALTER TABLE "cluster_nodes" ALTER COLUMN "memory_bytes" SET NOT NULL', $pStatements);
        $this->assertContains('ALTER TABLE "cluster_nodes" ALTER COLUMN "memory_bytes" SET DEFAULT 1073741824', $pStatements);
        $this->assertContains('ALTER TABLE "cluster_nodes" ADD COLUMN "rack_unit" VARCHAR(32) NULL', $pStatements);
        $this->assertContains('ALTER TABLE "cluster_nodes" DROP CONSTRAINT IF EXISTS "fk_nodes_cluster"', $pStatements);
        $this->assertContains('ALTER TABLE "cluster_nodes" DROP CONSTRAINT IF EXISTS "uniq_nodes_ip_port"', $pStatements);
        $this->assertContains('DROP INDEX IF EXISTS "idx_nodes_region"', $pStatements);
        $this->assertContains('ALTER TABLE "cluster_nodes" DROP COLUMN IF EXISTS "deprecated_metric_a"', $pStatements);
        $this->assertContains('ALTER TABLE "cluster_nodes" DROP COLUMN IF EXISTS "deprecated_metric_b"', $pStatements);
    }

    // =========================================================================
    // SECTION 2: AI Multi-Provider Routing & Resilient Fallback Stress
    // =========================================================================

    public function testAiRoutingWithInvalidProviderNameFallsBackToChain(): void
    {
        $engine = new OshimLlmEngine('oshim-sovereign-7b');
        
        // Pass a completely unknown/invalid provider name
        $res = $engine->generate('System self-check', [
            'provider' => 'invalid_unregistered_hyper_ai_3000'
        ]);

        $this->assertSame('COMPLETED', $res['status']);
        $this->assertNotEmpty($res['reply']);
        $this->assertTrue($res['fallback_occurred'], 'Should fallback when provider is invalid');
        $this->assertTrue(in_array($res['provider'], ['openai', 'anthropic', 'gemini', 'ollama', 'local_gguf'], true));
    }

    public function testAiCaseInsensitiveModelPrefixRouting(): void
    {
        $engine = new OshimLlmEngine();
        $openai = new OpenAiProvider('mock-key', 'gpt-4o-mini', ['sandbox' => true]);
        $anthropic = new AnthropicProvider('mock-key', 'claude-3-5-sonnet', ['sandbox' => true]);
        $gemini = new GeminiProvider('mock-key', 'gemini-1.5-flash', ['sandbox' => true]);
        $ollama = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', ['sandbox' => true]);

        $engine->registerProvider($openai);
        $engine->registerProvider($anthropic);
        $engine->registerProvider($gemini);
        $engine->registerProvider($ollama);

        // Uppercase & mixed-case model names
        $res1 = $engine->generate('Prompt', ['model' => 'GPT-4O-MINI']);
        $this->assertSame('openai', $res1['provider']);

        $res2 = $engine->generate('Prompt', ['model' => 'Claude-3-5-Sonnet-20241022']);
        $this->assertSame('anthropic', $res2['provider']);

        $res3 = $engine->generate('Prompt', ['model' => 'GEMINI-1.5-FLASH']);
        $this->assertSame('gemini', $res3['provider']);

        $res4 = $engine->generate('Prompt', ['model' => 'Llama3.2:1b']);
        $this->assertSame('ollama', $res4['provider']);
    }

    public function testAiDeepFallbackChainWhenAllRemoteProvidersThrow500(): void
    {
        $engine = new OshimLlmEngine();

        // 4 Mock providers throwing different error types
        $p1 = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'p1_auth_error'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $p, array $o = []): string { throw new RuntimeException('401 Unauthorized'); }
            public function embed(string $t): array { return []; }
        };
        $p2 = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'p2_rate_limit'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $p, array $o = []): string { throw new RuntimeException('429 Too Many Requests'); }
            public function embed(string $t): array { return []; }
        };
        $p3 = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'p3_server_down'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $p, array $o = []): string { throw new Exception('503 Service Unavailable'); }
            public function embed(string $t): array { return []; }
        };
        $p4 = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'p4_corrupted_response'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $p, array $o = []): string { throw new Error('Fatal internal buffer corruption'); }
            public function embed(string $t): array { return []; }
        };

        $engine->registerProvider($p1);
        $engine->registerProvider($p2);
        $engine->registerProvider($p3);
        $engine->registerProvider($p4);

        $engine->setFallbackChain([
            'p1_auth_error',
            'p2_rate_limit',
            'p3_server_down',
            'p4_corrupted_response',
            'local_gguf'
        ]);

        $res = $engine->generate('Autonomous cluster state', ['provider' => 'p1_auth_error']);

        $this->assertSame('COMPLETED', $res['status']);
        $this->assertSame('local_gguf', $res['provider']);
        $this->assertTrue($res['fallback_occurred']);
        $this->assertTrue($res['fallback_used']);
        $this->assertNotEmpty($res['reply']);
    }

    public function testAiChatHistoryRetentionDuringCascadingFailover(): void
    {
        $engine = new OshimLlmEngine();
        $engine->clearChatHistory();

        // Ensure history records correctly after a failover
        $res = $engine->generate('Initialize DNS mesh');
        $history = $engine->getChatHistory();

        $this->assertCount(2, $history);
        $this->assertSame('user', $history[0]['role']);
        $this->assertSame('Initialize DNS mesh', $history[0]['content']);
        $this->assertSame('assistant', $history[1]['role']);
        $this->assertSame($res['reply'], $history[1]['content']);
    }

    // =========================================================================
    // SECTION 3: GGUF BPE Tokenizer, Special Tokens, Multilingual & Dense Embeddings
    // =========================================================================

    public function testGgufAllCanonicalSpecialTokensAndCustomRegistration(): void
    {
        GgufTokenizer::reset();

        $specials = [
            '<unk>'             => 0,
            '<s>'               => 1,
            '</s>'              => 2,
            '<bos>'             => 1,
            '<eos>'             => 2,
            '[INST]'            => 3,
            '[/INST]'           => 4,
            '<pad>'             => 32000,
            '<|im_start|>'      => 32001,
            '<|im_end|>'        => 32002,
            '<|begin_of_text|>' => 128000,
            '<|end_of_text|>'   => 128001,
            '<|eot_id|>'        => 128009,
        ];

        foreach ($specials as $tok => $expectedId) {
            $tokens = GgufTokenizer::encode($tok);
            $this->assertContains($expectedId, $tokens, "Token '{$tok}' must encode to {$expectedId}");
        }

        // Custom Special Token Registration
        GgufTokenizer::registerSpecialToken('<|sovereign_kernel_v1|>', 99999);
        $encodedCustom = GgufTokenizer::encode('<|im_start|> system <|sovereign_kernel_v1|> test <|im_end|>');
        $this->assertContains(32001, $encodedCustom);
        $this->assertContains(99999, $encodedCustom);
        $this->assertContains(32002, $encodedCustom);

        $decoded = GgufTokenizer::decode($encodedCustom);
        $this->assertStringContainsString('<|im_start|>', $decoded);
        $this->assertStringContainsString('<|sovereign_kernel_v1|>', $decoded);
        $this->assertStringContainsString('<|im_end|>', $decoded);
    }

    public function testGgufMultilingualCorporaTokenizationAndRoundtrip(): void
    {
        GgufTokenizer::reset();

        $multilingualTexts = [
            'Bengali' => 'বাংলা ভাষা ও সার্বভৌম ক্লাউড কম্পিউটিং ফ্রেমওয়ার্ক',
            'Arabic'  => 'السحابة السيادية والأمن السيبراني المتقدم',
            'Japanese'=> 'ソブリンクラウドと高速ネットワーク処理',
            'Chinese' => '主权云计算系统与高性能微内核架构',
            'Russian' => 'Суверенное облако и виртуализация Linux KVM',
            'Mixed'   => '👑 OSHIM Sovereign Cloud ⚡ 2026 🛡️ Bengali: বাংলা, Arabic: سحاب',
        ];

        foreach ($multilingualTexts as $lang => $text) {
            $tokenIds = GgufTokenizer::encode($text);
            $this->assertNotEmpty($tokenIds, "Tokenization for {$lang} must produce tokens");
            $decoded = GgufTokenizer::decode($tokenIds);
            $this->assertNotEmpty($decoded, "Decoded text for {$lang} must not be empty");
        }
    }

    public function testGgufRawBinaryAndInvalidUtf8ByteFallback(): void
    {
        GgufTokenizer::reset();

        // Arbitrary non-UTF8 binary stream
        $rawBinary = "\x80\xFF\xFE\x01\x02\xFD\xAA\x55";
        $tokenIds = GgufTokenizer::encode($rawBinary);

        $this->assertCount(strlen($rawBinary), $tokenIds, 'Each invalid byte should map to a token ID');
        $decoded = GgufTokenizer::decode($tokenIds);
        $this->assertSame($rawBinary, $decoded, 'Byte fallback must round-trip exactly');
    }

    public function testDenseNeuralEmbeddingsHighDimensionalUnitNormAndCosSimilarity(): void
    {
        GgufTokenizer::reset();

        $textA = 'Linux microVM hypervisor virtualization node';
        $textB = 'Linux microVM hypervisor virtualization cluster';
        $textC = 'Banana apple strawberry orange fruit salad';

        $dims = [64, 128, 256, 512, 1024];

        foreach ($dims as $dim) {
            $vecA = GgufTokenizer::embed($textA, $dim);
            $vecB = GgufTokenizer::embed($textB, $dim);
            $vecC = GgufTokenizer::embed($textC, $dim);

            $this->assertCount($dim, $vecA);
            $this->assertCount($dim, $vecB);
            $this->assertCount($dim, $vecC);

            // Unit norm validation: ||v|| = 1.0
            $normA = MatrixMath::vectorMagnitude($vecA);
            $normB = MatrixMath::vectorMagnitude($vecB);
            $normC = MatrixMath::vectorMagnitude($vecC);

            $this->assertTrue(abs($normA - 1.0) < 1e-4, "Dimension {$dim}: Norm A must be 1.0");
            $this->assertTrue(abs($normB - 1.0) < 1e-4, "Dimension {$dim}: Norm B must be 1.0");
            $this->assertTrue(abs($normC - 1.0) < 1e-4, "Dimension {$dim}: Norm C must be 1.0");

            // Semantic similarity check: sim(A, B) > sim(A, C)
            $simAB = MatrixMath::cosineSimilarity($vecA, $vecB);
            $simAC = MatrixMath::cosineSimilarity($vecA, $vecC);

            $this->assertTrue($simAB > $simAC, "Dimension {$dim}: Related texts (A,B) must have higher cosine similarity ({$simAB}) than unrelated (A,C) ({$simAC})");
        }
    }

    // =========================================================================
    // SECTION 4: CLI Command Execution Stress with Anomalous Arguments
    // =========================================================================

    public function testCliAnomalousArgumentParsingStress(): void
    {
        $argvCombinations = [
            ['oshim', 'serve', '--host=', '--port=0', '--unknown-flag', '-x', '-y', '-z'],
            ['oshim', 'test', '--filter="Adversarial*"', '--stop-on-failure', '--verbose=2'],
            ['oshim', 'key:generate', '--show', '--force', '--env=.env.testing'],
            ['oshim', 'ai:chat', '--provider=openai', '--model=gpt-4o', '--temperature=0.0', 'Hello world with "quotes" and symbols !@#$%^&*()'],
            ['oshim', 'billing:cron', '--dry-run', '--force', '--date=2026-08-31'],
            ['oshim', 'dns:serve', '--port=5353', '--protocol=udp', '--bind=127.0.0.1'],
            ['oshim', 's3:serve', '--port=9000', '--storage-dir=/tmp/test_s3'],
            ['oshim', 'ssl:issue', '--domain=example.com', '--email=admin@example.com', '--challenge=http-01'],
            ['oshim', 'vm:spawn', '--name=vm-stress-01', '--vcpus=4', '--memory=4096', '--image=alpine-3.20'],
        ];

        foreach ($argvCombinations as $argv) {
            $input = new Input($argv);
            $this->assertNotEmpty($input->getCommandName());
            $this->assertIsArray($input->getOptions());
            $this->assertIsArray($input->getArguments());
        }
    }

    public function testCliExecutionOfAllRegisteredSystemCommandsWithHelp(): void
    {
        $commands = [
            new ServeCommand(),
            new TestCommand(),
            new MigrateCommand(),
            new RollbackCommand(),
            new SeedCommand(),
            new MakeModelCommand(),
            new MakeMigrationCommand(),
            new MakeControllerCommand(),
            new MakeComponentCommand(),
            new KeyGenerateCommand(),
            new UniversalInfoCommand(),
            new TurboServeCommand(),
            new TurboBenchCommand(),
            new MobileBuildCommand(),
            new DesktopServeCommand(),
            new AiChatCommand(),
            new AiRagCommand(),
            new AiTeamCommand(),
            new PdfInvoiceCommand(),
            new TotpQrCommand(),
            new QueueWorkCommand(),
            new CacheClearCommand(),
            new AppCreateCommand(),
            new AppBundleCommand(),
            new AppRunCommand(),
            new BillingCronCommand(),
            new DnsServeCommand(),
            new DnsStartCommand(),
            new NodeStartCommand(),
            new S3ServeCommand(),
            new SslIssueCommand(),
            new VmSpawnCommand(),
            new ScheduleRunCommand(),
            new MakeCrudCommand(),
        ];

        $cli = new CliApplication();
        foreach ($commands as $cmd) {
            $cli->register($cmd);
        }

        // Test running each command with --help
        foreach ($commands as $cmd) {
            $name = $cmd->getName();
            ob_start();
            $code = $cli->run(['oshim', $name, '--help']);
            $out = ob_get_clean();

            $this->assertSame(0, $code, "Command '{$name}' --help must exit with 0");
            $this->assertStringContainsString('Usage:', $out);
        }
    }

    public function testSchemaCompilersSpecialCharactersAndLiteralEscapingInDefaults(): void
    {
        $mysql = new MysqlSchemaCompiler();
        $postgres = new PostgresSchemaCompiler();

        // 1. Literal quotes in defaults
        $blueprint = new Blueprint('user_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('author')->default("O'Reilly");
            $t->string('quote')->default('He said "Hello" and \'Goodbye\'');
            $t->timestamp('verified_at')->default('CURRENT_TIMESTAMP');
            $t->boolean('is_verified')->nullable()->default(null);
            $t->boolean('is_flagged')->default(false);
            $t->boolean('is_admin')->default(true);
        });

        $mSql = $mysql->compileCreate($blueprint)[0];
        $this->assertStringContainsString("DEFAULT 'O\'Reilly'", $mSql);
        $this->assertStringContainsString("DEFAULT 'He said \\\"Hello\\\" and \'Goodbye\''", $mSql);
        $this->assertStringContainsString("DEFAULT CURRENT_TIMESTAMP", $mSql);
        $this->assertStringContainsString("DEFAULT NULL", $mSql);
        $this->assertStringContainsString("DEFAULT 0", $mSql);
        $this->assertStringContainsString("DEFAULT 1", $mSql);

        $pSql = $postgres->compileCreate($blueprint)[0];
        $this->assertStringContainsString("DEFAULT 'O\'Reilly'", $pSql);
        $this->assertStringContainsString("DEFAULT 'He said \\\"Hello\\\" and \'Goodbye\''", $pSql);
        $this->assertStringContainsString("DEFAULT CURRENT_TIMESTAMP", $pSql);
        $this->assertStringContainsString("DEFAULT NULL", $pSql);
        $this->assertStringContainsString("DEFAULT FALSE", $pSql);
        $this->assertStringContainsString("DEFAULT TRUE", $pSql);

        // 2. Wrap escaping
        $this->assertSame("`table``name`", $mysql->wrap("table`name"));
        $this->assertSame('"table""name"', $postgres->wrap('table"name'));
    }

    public function testSchemaCompilersInspectionAndDropSyntaxCrossEngine(): void
    {
        $mysql = new MysqlSchemaCompiler();
        $postgres = new PostgresSchemaCompiler();

        // MySQL inspection
        $this->assertSame("DROP TABLE `audit_logs`", $mysql->compileDrop('audit_logs'));
        $this->assertSame("DROP TABLE IF EXISTS `audit_logs`", $mysql->compileDropIfExists('audit_logs'));
        $this->assertSame("RENAME TABLE `audit_logs` TO `archive_audit_logs`", $mysql->compileRename('audit_logs', 'archive_audit_logs'));
        $this->assertStringContainsString("information_schema.tables", $mysql->compileTableExists('audit_logs'));
        $this->assertStringContainsString("information_schema.columns", $mysql->compileColumnExists('audit_logs', 'id'));

        // Postgres inspection
        $this->assertSame('DROP TABLE "audit_logs"', $postgres->compileDrop('audit_logs'));
        $this->assertSame('DROP TABLE IF EXISTS "audit_logs"', $postgres->compileDropIfExists('audit_logs'));
        $this->assertSame('ALTER TABLE "audit_logs" RENAME TO "archive_audit_logs"', $postgres->compileRename('audit_logs', 'archive_audit_logs'));
        $this->assertStringContainsString("pg_tables", $postgres->compileTableExists('audit_logs'));
        $this->assertStringContainsString("information_schema.columns", $postgres->compileColumnExists('audit_logs', 'id'));
    }

    public function testAiEngineHighFrequencyBurstAndMemoryStability(): void
    {
        $engine = new OshimLlmEngine('oshim-sovereign-7b');
        $initialMemory = memory_get_usage();

        for ($i = 1; $i <= 30; $i++) {
            $res = $engine->generate("Stress query iteration {$i} for cloud metrics", [
                'temperature' => 0.1 * ($i % 10),
                'max_tokens' => 64 + ($i * 2),
            ]);

            $this->assertSame('COMPLETED', $res['status']);
            $this->assertNotEmpty($res['reply']);
            $this->assertTrue($res['input_tokens'] > 0);
            $this->assertTrue($res['output_tokens'] > 0);
            $this->assertTrue($res['tokens_per_second'] >= 0.0);
            $this->assertTrue($res['inference_time_seconds'] >= 0.0);
        }

        $history = $engine->getHistory();
        $this->assertCount(60, $history, '30 user + 30 assistant messages should be in history');

        $engine->clearHistory();
        $this->assertEmpty($engine->getHistory());

        $finalMemory = memory_get_usage();
        $memoryDeltaMb = ($finalMemory - $initialMemory) / (1024 * 1024);
        $this->assertTrue($memoryDeltaMb < 10.0, "Memory delta after 30 iterations should be < 10MB (was {$memoryDeltaMb}MB)");
    }

    public function testGgufTokenizerConsecutiveSpecialTokensAndWordBoundaries(): void
    {
        GgufTokenizer::reset();

        // 1. Consecutive special tokens
        $consecutiveSpecials = "<bos><s>[INST][/INST]</s><eos><pad><|im_start|><|im_end|><|begin_of_text|><|end_of_text|><|eot_id|>";
        $tokenIds = GgufTokenizer::encode($consecutiveSpecials);
        $this->assertNotEmpty($tokenIds);

        // Verify each special token ID is represented
        $expected = [1, 1, 3, 4, 2, 2, 32000, 32001, 32002, 128000, 128001, 128009];
        foreach ($expected as $expId) {
            $this->assertContains($expId, $tokenIds);
        }

        $decoded = GgufTokenizer::decode($tokenIds);
        $this->assertStringContainsString('[INST]', $decoded);
        $this->assertStringContainsString('[/INST]', $decoded);
        $this->assertStringContainsString('<|im_start|>', $decoded);
        $this->assertStringContainsString('<|im_end|>', $decoded);

        // 2. Empty string encode/decode
        $this->assertSame([], GgufTokenizer::encode(''));
        $this->assertSame('', GgufTokenizer::decode([]));

        // 3. High entropy binary byte stream fuzzing
        $rawBytes = random_bytes(256);
        $encodedBytes = GgufTokenizer::encode($rawBytes);
        $this->assertCount(256, $encodedBytes);
        $decodedBytes = GgufTokenizer::decode($encodedBytes);
        $this->assertSame($rawBytes, $decodedBytes, 'Random binary fuzzing must round-trip exactly');
    }

    public function testGgufHeaderBinaryParsingWithValidAndCorruptedHeaders(): void
    {
        $tmpDir = sys_get_temp_dir() . '/gguf_test_' . uniqid();
        mkdir($tmpDir, 0777, true);

        $validGgufPath = $tmpDir . '/model_valid.gguf';
        $corruptedPath = $tmpDir . '/model_bad.gguf';
        $nonExistentPath = $tmpDir . '/non_existent.gguf';

        try {
            // Write simulated valid GGUF header (Magic 'GGUF', Version 3 uint32, tensorCount uint64, kvCount uint64)
            $validHeader = 'GGUF' . pack('V', 3) . pack('P', 145) . pack('P', 28);
            file_put_contents($validGgufPath, $validHeader);

            // Write corrupted header
            file_put_contents($corruptedPath, 'BADM' . pack('V', 1));

            // Test valid
            $parsed = GgufTokenizer::loadFromGgufFile($validGgufPath);
            $this->assertIsArray($parsed);
            $this->assertSame('GGUF', $parsed['format']);
            $this->assertSame(3, $parsed['version']);
            $this->assertSame(145, $parsed['tensor_count']);
            $this->assertSame(28, $parsed['kv_count']);

            // Test corrupted
            $badParsed = GgufTokenizer::loadFromGgufFile($corruptedPath);
            $this->assertNull($badParsed);

            // Test non-existent
            $missingParsed = GgufTokenizer::loadFromGgufFile($nonExistentPath);
            $this->assertNull($missingParsed);
        } finally {
            if (file_exists($validGgufPath)) unlink($validGgufPath);
            if (file_exists($corruptedPath)) unlink($corruptedPath);
            if (is_dir($tmpDir)) rmdir($tmpDir);
        }
    }

    public function testCliExecutionOfDiagnosticAndInfoCommands(): void
    {
        $cli = new CliApplication();
        $cli->register(new UniversalInfoCommand())
            ->register(new CacheClearCommand())
            ->register(new KeyGenerateCommand());

        // 1. kernel:info
        ob_start();
        $code1 = $cli->run(['oshim', 'kernel:info']);
        $out1 = ob_get_clean();
        $this->assertSame(0, $code1);
        $this->assertNotEmpty($out1);

        // 2. cache:clear
        ob_start();
        $code2 = $cli->run(['oshim', 'cache:clear']);
        $out2 = ob_get_clean();
        $this->assertSame(0, $code2);
        $this->assertNotEmpty($out2);

        // 3. key:generate --show
        ob_start();
        $code3 = $cli->run(['oshim', 'key:generate', '--show']);
        $out3 = ob_get_clean();
        $this->assertSame(0, $code3);
        $this->assertNotEmpty($out3);
    }
}

