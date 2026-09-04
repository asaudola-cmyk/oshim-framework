<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Database\ConnectionManager;
use Oshim\Database\Schema\Schema;
use Oshim\Database\Schema\Blueprint;
use Oshim\Database\ORM\Model;
use Oshim\Database\ORM\Collection;
use Oshim\Database\ORM\Relations\HasMany;
use Oshim\Database\ORM\Relations\HasOne;
use Oshim\Database\ORM\Relations\BelongsTo;
use Oshim\Database\ORM\Relations\BelongsToMany;
use Oshim\Database\ORM\Traits\SoftDeletes;

class DbTestUser extends Model
{
    use SoftDeletes;

    protected string $table = 'db_test_users';
    protected array $fillable = ['name', 'email'];

    public function posts(): HasMany
    {
        return $this->hasMany(DbTestPost::class, 'user_id', 'id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(DbTestProfile::class, 'user_id', 'id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(DbTestRole::class, 'db_test_user_roles', 'user_id', 'role_id');
    }
}

class DbTestPost extends Model
{
    use SoftDeletes;

    protected string $table = 'db_test_posts';
    protected array $fillable = ['user_id', 'title', 'content'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(DbTestUser::class, 'user_id', 'id');
    }
}

class DbTestProfile extends Model
{
    protected string $table = 'db_test_profiles';
    protected array $fillable = ['user_id', 'bio'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(DbTestUser::class, 'user_id', 'id');
    }
}

class DbTestRole extends Model
{
    protected string $table = 'db_test_roles';
    protected array $fillable = ['name', 'slug'];
}

class DatabaseTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        ConnectionManager::getInstance()->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ], 'default');

        Schema::dropIfExists('db_test_user_roles');
        Schema::dropIfExists('db_test_profiles');
        Schema::dropIfExists('db_test_posts');
        Schema::dropIfExists('db_test_roles');
        Schema::dropIfExists('db_test_users');

        Schema::create('db_test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('db_test_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->text('content')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('db_test_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->text('bio')->nullable();
            $table->timestamps();
        });

        Schema::create('db_test_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });

        Schema::create('db_test_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('role_id');
        });
    }

    public function testEagerLoadingWithClosureConstraints(): void
    {
        $u1 = DbTestUser::create(['name' => 'User One', 'email' => 'one@test.local']);
        $p1 = DbTestPost::create(['user_id' => $u1->id, 'title' => 'Alpha Post', 'content' => 'Active']);
        $p2 = DbTestPost::create(['user_id' => $u1->id, 'title' => 'Beta Post', 'content' => 'Deleted']);
        $p2->delete();

        // Default eager loading excludes soft deleted
        $usersDefault = DbTestUser::with('posts')->where('id', '=', $u1->id)->get();
        $this->assertCount(1, $usersDefault);
        $this->assertNotNull($usersDefault->first());
        $this->assertCount(1, $usersDefault->first()?->posts ?? []);

        // Closure constrained eager loading with withTrashed()
        $usersWithTrashed = DbTestUser::with(['posts' => function ($q) {
            $q->withTrashed();
        }])->where('id', '=', $u1->id)->get();

        $this->assertCount(1, $usersWithTrashed);
        $this->assertNotNull($usersWithTrashed->first());
        $this->assertCount(2, $usersWithTrashed->first()?->posts ?? []);

        // Closure constrained eager loading with where condition
        $usersFiltered = DbTestUser::with(['posts' => function ($q) {
            $q->where('title', 'like', 'Alpha%');
        }])->where('id', '=', $u1->id)->get();

        $this->assertCount(1, $usersFiltered);
        $this->assertNotNull($usersFiltered->first());
        $this->assertCount(1, $usersFiltered->first()?->posts ?? []);
        $this->assertEquals('Alpha Post', $usersFiltered->first()?->posts?->first()?->title);
    }

    public function testBelongsToEagerLoadingMultiRecords(): void
    {
        $u1 = DbTestUser::create(['name' => 'Alice', 'email' => 'alice@test.local']);
        $u2 = DbTestUser::create(['name' => 'Bob', 'email' => 'bob@test.local']);

        $p1 = DbTestPost::create(['user_id' => $u1->id, 'title' => 'Alice Post 1']);
        $p2 = DbTestPost::create(['user_id' => $u1->id, 'title' => 'Alice Post 2']);
        $p3 = DbTestPost::create(['user_id' => $u2->id, 'title' => 'Bob Post 1']);

        $posts = DbTestPost::with('user')->get();
        $this->assertCount(3, $posts);

        $this->assertTrue($posts[0]->relationLoaded('user'));
        $this->assertNotNull($posts[0]->user);
        $this->assertEquals('Alice', $posts[0]->user->name);

        $this->assertTrue($posts[1]->relationLoaded('user'));
        $this->assertNotNull($posts[1]->user);
        $this->assertEquals('Alice', $posts[1]->user->name);

        $this->assertTrue($posts[2]->relationLoaded('user'));
        $this->assertNotNull($posts[2]->user);
        $this->assertEquals('Bob', $posts[2]->user->name);
    }

    public function testHasOneEagerLoadingMultiRecords(): void
    {
        $u1 = DbTestUser::create(['name' => 'Alice', 'email' => 'alice@test.local']);
        $u2 = DbTestUser::create(['name' => 'Bob', 'email' => 'bob@test.local']);
        $u3 = DbTestUser::create(['name' => 'Charlie', 'email' => 'charlie@test.local']);

        DbTestProfile::create(['user_id' => $u1->id, 'bio' => 'Alice Bio']);
        DbTestProfile::create(['user_id' => $u2->id, 'bio' => 'Bob Bio']);
        DbTestProfile::create(['user_id' => $u3->id, 'bio' => 'Charlie Bio']);

        $users = DbTestUser::with('profile')->get();
        $this->assertCount(3, $users);

        $this->assertTrue($users[0]->relationLoaded('profile'));
        $this->assertNotNull($users[0]->profile);
        $this->assertEquals('Alice Bio', $users[0]->profile->bio);

        $this->assertTrue($users[1]->relationLoaded('profile'));
        $this->assertNotNull($users[1]->profile);
        $this->assertEquals('Bob Bio', $users[1]->profile->bio);

        $this->assertTrue($users[2]->relationLoaded('profile'));
        $this->assertNotNull($users[2]->profile);
        $this->assertEquals('Charlie Bio', $users[2]->profile->bio);
    }

    public function testModelWithTrashedAndOnlyTrashedChaining(): void
    {
        $u1 = DbTestUser::create(['name' => 'Active User', 'email' => 'active@test.local']);
        $u2 = DbTestUser::create(['name' => 'Deleted User', 'email' => 'deleted@test.local']);
        $u2->delete();

        DbTestPost::create(['user_id' => $u1->id, 'title' => 'Post 1']);
        DbTestPost::create(['user_id' => $u2->id, 'title' => 'Post 2']);

        // withTrashed()->with('posts')->get()
        $allUsers = DbTestUser::withTrashed()->with('posts')->get();
        $this->assertCount(2, $allUsers);
        $this->assertInstanceOf(Collection::class, $allUsers);
        $this->assertEquals('Active User', $allUsers[0]->name);
        $this->assertEquals('Deleted User', $allUsers[1]->name);

        // onlyTrashed()->with('posts')->get()
        $deletedUsers = DbTestUser::onlyTrashed()->with('posts')->get();
        $this->assertCount(1, $deletedUsers);
        $this->assertEquals('Deleted User', $deletedUsers[0]->name);

        // withTrashed()->where(...)->first()
        $found = DbTestUser::withTrashed()->where('id', '=', $u2->id)->first();
        $this->assertNotNull($found);
        $this->assertEquals('Deleted User', $found->name);

        // onlyTrashed()->find(...)
        $foundOnly = DbTestUser::onlyTrashed()->find($u2->id);
        $this->assertNotNull($foundOnly);
        $this->assertEquals('Deleted User', $foundOnly->name);

        // count() forwarding
        $this->assertEquals(2, DbTestUser::withTrashed()->count());
        $this->assertEquals(1, DbTestUser::onlyTrashed()->count());
    }

    public function testRelationDirectGetHydration(): void
    {
        $u1 = DbTestUser::create(['name' => 'Alice', 'email' => 'alice@test.local']);
        $p1 = DbTestPost::create(['user_id' => $u1->id, 'title' => 'Post 1', 'content' => 'C1']);
        $p2 = DbTestPost::create(['user_id' => $u1->id, 'title' => 'Post 2', 'content' => 'C2']);
        $prof = DbTestProfile::create(['user_id' => $u1->id, 'bio' => 'Alice Bio']);
        $role = DbTestRole::create(['name' => 'Admin', 'slug' => 'admin']);
        $u1->roles()->attach($role->id);

        // HasMany::get()
        $posts = $u1->posts()->get();
        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(2, $posts);
        $this->assertInstanceOf(DbTestPost::class, $posts[0]);
        $this->assertEquals('Post 1', $posts[0]->title);

        // HasOne::get()
        $profiles = $u1->profile()->get();
        $this->assertInstanceOf(Collection::class, $profiles);
        $this->assertCount(1, $profiles);
        $this->assertInstanceOf(DbTestProfile::class, $profiles[0]);
        $this->assertEquals('Alice Bio', $profiles[0]->bio);

        // BelongsTo::get()
        $users = $p1->user()->get();
        $this->assertInstanceOf(Collection::class, $users);
        $this->assertCount(1, $users);
        $this->assertInstanceOf(DbTestUser::class, $users[0]);
        $this->assertEquals('Alice', $users[0]->name);

        // BelongsToMany::get()
        $roles = $u1->roles()->get();
        $this->assertInstanceOf(Collection::class, $roles);
        $this->assertCount(1, $roles);
        $this->assertInstanceOf(DbTestRole::class, $roles[0]);
        $this->assertEquals('Admin', $roles[0]->name);
    }
}
