<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use Oshim\Testing\TestCase;
use Oshim\Database\ConnectionManager;
use Oshim\Database\DB;
use Oshim\Database\Schema\Schema;
use Oshim\Database\Schema\Blueprint;
use Oshim\Database\Migrations\MigrationRepository;
use Oshim\Database\Migrations\Migrator;
use Oshim\Database\Migrations\Migration;

class MigrationSchemaTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        ConnectionManager::getInstance()->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ], 'default');
    }

    public function testSchemaCreateAndTableInspectors(): void
    {
        $this->assertFalse(Schema::hasTable('invoices'));

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->decimal('total_amount', 12, 2);
            $table->string('status')->default('pending');
            $table->boolean('is_paid')->default(false);
            $table->json('items')->nullable();
            $table->timestamps();
        });

        $this->assertTrue(Schema::hasTable('invoices'));
        $this->assertTrue(Schema::hasColumn('invoices', 'invoice_number'));
        $this->assertTrue(Schema::hasColumn('invoices', 'total_amount'));
        $this->assertTrue(Schema::hasColumn('invoices', 'is_paid'));
        $this->assertFalse(Schema::hasColumn('invoices', 'non_existent_column'));

        Schema::dropIfExists('invoices');
        $this->assertFalse(Schema::hasTable('invoices'));
    }

    public function testMigrationRepositoryAndMigratorWorkflow(): void
    {
        $connection = DB::connection();
        $repo = new MigrationRepository($connection);
        $repo->createRepository();

        $this->assertTrue($repo->repositoryExists());
        $this->assertEquals([], $repo->getRan());
        $this->assertEquals(1, $repo->getNextBatchNumber());

        $tempDir = sys_get_temp_dir() . '/oshim_test_migrations_' . bin2hex(random_bytes(4));
        mkdir($tempDir, 0755, true);

        // Create sample migration file
        $migCode = <<<PHP
<?php
use Oshim\Database\Migrations\Migration;
use Oshim\Database\Schema\Schema;
use Oshim\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        Schema::create('test_products', function (Blueprint \$table) {
            \$table->id();
            \$table->string('title');
            \$table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('test_products');
    }
};
PHP;
        file_put_contents("{$tempDir}/2026_08_29_000001_create_test_products.php", $migCode);

        $migrator = new Migrator($repo, $connection);

        // Run migrations
        $executed = $migrator->run([$tempDir]);
        $this->assertCount(1, $executed);
        $this->assertTrue(Schema::hasTable('test_products'));
        $this->assertContains('2026_08_29_000001_create_test_products', $repo->getRan());

        // Status
        $status = $migrator->status([$tempDir]);
        $this->assertCount(1, $status);
        $this->assertTrue($status[0]['ran']);

        // Rollback migrations
        $rolledBack = $migrator->rollback([$tempDir], 1);
        $this->assertCount(1, $rolledBack);
        $this->assertFalse(Schema::hasTable('test_products'));
        $this->assertEquals([], $repo->getRan());

        // Cleanup
        unlink("{$tempDir}/2026_08_29_000001_create_test_products.php");
        rmdir($tempDir);
    }
}
