<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use Oshim\Testing\TestCase;
use Oshim\Database\ORM\Model;
use Oshim\Database\ConnectionManager;
use Oshim\Database\Pagination\LengthAwarePaginator;

class HookTestModel extends Model
{
    protected string $table = 'hook_test_models';
    protected array $fillable = ['name', 'status'];

    public static bool $creatingFired = false;
    public static bool $createdFired = false;

    protected function onCreating(): bool
    {
        self::$creatingFired = true;
        return true;
    }

    protected function onCreated(): void
    {
        self::$createdFired = true;
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', 'active');
    }
}

class OrmPaginationAndHooksTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $db = ConnectionManager::getInstance()->connection();
        $db->statement("CREATE TABLE IF NOT EXISTS hook_test_models (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255),
            status VARCHAR(50),
            created_at DATETIME,
            updated_at DATETIME
        )");
        $db->statement("DELETE FROM hook_test_models");
    }

    public function testModelLifecycleHooksAndScopes(): void
    {
        HookTestModel::$creatingFired = false;
        HookTestModel::$createdFired = false;

        $model = new HookTestModel(['name' => 'Server 1', 'status' => 'active']);
        $model->save();

        $this->assertTrue(HookTestModel::$creatingFired);
        $this->assertTrue(HookTestModel::$createdFired);

        // Add more records
        for ($i = 2; $i <= 10; $i++) {
            (new HookTestModel(['name' => "Server {$i}", 'status' => $i % 2 === 0 ? 'active' : 'inactive']))->save();
        }

        // Test scope
        $activeModels = HookTestModel::where('status', '=', 'active')->get();
        $this->assertCount(6, $activeModels);

        // Test Pagination
        $paginator = HookTestModel::paginate(3, 1);
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(10, $paginator->total());
        $this->assertSame(3, $paginator->perPage());
        $this->assertSame(1, $paginator->currentPage());
        $this->assertSame(4, $paginator->lastPage());
        $this->assertTrue($paginator->hasMorePages());
        $this->assertCount(3, $paginator->items());

        $page2 = HookTestModel::paginate(3, 2);
        $this->assertSame(2, $page2->currentPage());
        $this->assertCount(3, $page2->items());
    }
}
