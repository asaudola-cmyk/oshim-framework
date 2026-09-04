<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use Oshim\Testing\TestCase;
use Oshim\Database\ORM\Model;
use Oshim\Database\ORM\Relations\HasMany;
use Oshim\Database\ORM\Relations\BelongsTo;
use Oshim\Database\ORM\Relations\BelongsToMany;
use Oshim\Database\ORM\Traits\SoftDeletes;
use Oshim\Database\ConnectionManager;
use Oshim\Database\Schema\Schema;
use Oshim\Database\Schema\Blueprint;

// Test Models
class TestUser extends Model
{
    protected string $table = 'test_users';
    protected array $fillable = ['name', 'email', 'settings', 'is_active'];
    protected array $casts = [
        'settings'  => 'array',
        'is_active' => 'bool',
    ];

    public function instances(): HasMany
    {
        return $this->hasMany(TestInstance::class, 'user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(TestRole::class, 'test_user_roles', 'user_id', 'role_id');
    }
}

class TestInstance extends Model
{
    use SoftDeletes;

    protected string $table = 'test_instances';
    protected array $fillable = ['user_id', 'hostname', 'cores', 'memory_mb', 'deleted_at'];
    protected array $casts = [
        'cores'     => 'int',
        'memory_mb' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }
}

class TestRole extends Model
{
    protected string $table = 'test_roles';
    protected array $fillable = ['name', 'slug'];
}

class ModelOrmTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        ConnectionManager::getInstance()->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ], 'default');

        // Create tables
        Schema::dropIfExists('test_user_roles');
        Schema::dropIfExists('test_instances');
        Schema::dropIfExists('test_roles');
        Schema::dropIfExists('test_users');

        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->text('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('test_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('hostname');
            $table->integer('cores')->default(1);
            $table->integer('memory_mb')->default(1024);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('test_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });

        Schema::create('test_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('role_id');
        });
    }

    public function testModelPersistenceAndCasting(): void
    {
        $user = new TestUser();
        $user->name = 'Shafiullah';
        $user->email = 'shafi@oshim.cloud';
        $user->settings = ['theme' => 'dark', 'notifications' => true];
        $user->is_active = true;

        $this->assertTrue($user->isDirty('name'));
        $this->assertTrue($user->save());

        $this->assertTrue($user->exists);
        $this->assertNotNull($user->id);
        $this->assertTrue($user->isClean());

        // Fetch back from database
        $found = TestUser::find($user->id);
        $this->assertNotNull($found);
        $this->assertEquals('Shafiullah', $found->name);
        $this->assertTrue($found->is_active);
        $this->assertEquals('dark', $found->settings['theme']);
    }

    public function testModelHasManyAndBelongsToRelationship(): void
    {
        $user = TestUser::create([
            'name'      => 'Cluster Admin',
            'email'     => 'admin@cluster.local',
            'is_active' => true,
        ]);

        $instance1 = TestInstance::create([
            'user_id'   => $user->id,
            'hostname'  => 'vps-node-01',
            'cores'     => 4,
            'memory_mb' => 8192,
        ]);

        $instance2 = TestInstance::create([
            'user_id'   => $user->id,
            'hostname'  => 'vps-node-02',
            'cores'     => 8,
            'memory_mb' => 16384,
        ]);

        $instances = $user->instances;
        $this->assertCount(2, $instances);
        $this->assertEquals('vps-node-01', $instances[0]->hostname);

        $owner = $instance1->user;
        $this->assertEquals('Cluster Admin', $owner->name);
    }

    public function testModelBelongsToManyPivotSync(): void
    {
        $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@test.com']);
        $roleAdmin = TestRole::create(['name' => 'Administrator', 'slug' => 'admin']);
        $roleEditor = TestRole::create(['name' => 'Editor', 'slug' => 'editor']);

        $user->roles()->attach($roleAdmin->id);
        $this->assertCount(1, $user->roles()->getResults());

        $user->roles()->sync([$roleAdmin->id, $roleEditor->id]);
        $this->assertCount(2, $user->roles()->getResults());

        $user->roles()->detach($roleAdmin->id);
        $this->assertCount(1, $user->roles()->getResults());
    }

    public function testModelSoftDeletes(): void
    {
        $user = TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $instance = TestInstance::create(['user_id' => $user->id, 'hostname' => 'temp-node']);

        $this->assertFalse($instance->trashed());

        $instance->delete();
        $this->assertTrue($instance->trashed());

        // Default query excludes trashed
        $this->assertNull(TestInstance::find($instance->id));

        // withTrashed and onlyTrashed
        $this->assertNotNull(TestInstance::withTrashed()->where('id', $instance->id)->first());
        $this->assertNotNull(TestInstance::onlyTrashed()->where('id', $instance->id)->first());

        // Restore instance
        $instance->restore();
        $this->assertFalse($instance->trashed());
        $this->assertNotNull(TestInstance::find($instance->id));
        $this->assertNull(TestInstance::onlyTrashed()->where('id', $instance->id)->first());
    }

    public function testEagerLoadingEliminatesNPlusOne(): void
    {
        $user1 = TestUser::create(['name' => 'User 1', 'email' => 'u1@test.com']);
        $user2 = TestUser::create(['name' => 'User 2', 'email' => 'u2@test.com']);

        TestInstance::create(['user_id' => $user1->id, 'hostname' => 'u1-node1']);
        TestInstance::create(['user_id' => $user2->id, 'hostname' => 'u2-node1']);

        $users = TestUser::with('instances')->get();
        $this->assertCount(2, $users);
        $this->assertTrue($users[0]->relationLoaded('instances'));
        $this->assertCount(1, $users[0]->instances);
    }
}
