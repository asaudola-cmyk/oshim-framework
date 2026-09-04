<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use Oshim\Testing\TestCase;
use Oshim\Database\ConnectionManager;
use Oshim\Database\Connection;
use Oshim\Database\DB;
use Oshim\Database\Schema\Schema;
use Oshim\Database\Schema\Blueprint;

class ConnectionQueryTest extends TestCase
{
    protected Connection $connection;

    public function setUp(): void
    {
        parent::setUp();

        ConnectionManager::getInstance()->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ], 'test_db');

        ConnectionManager::getInstance()->setDefaultConnection('test_db');
        $this->connection = DB::connection('test_db');

        // Create test tables
        Schema::connection('test_db')->dropIfExists('servers');
        Schema::connection('test_db')->create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address')->nullable();
            $table->integer('ram_mb');
            $table->string('status')->default('running');
            $table->timestamps();
        });
    }

    public function testQueryBuilderInsertAndSelect(): void
    {
        $id1 = DB::table('servers')->insertGetId([
            'name'       => 'node-alpha',
            'ip_address' => '10.0.0.1',
            'ram_mb'     => 4096,
            'status'     => 'running',
        ]);

        $id2 = DB::table('servers')->insertGetId([
            'name'       => 'node-beta',
            'ip_address' => '10.0.0.2',
            'ram_mb'     => 8192,
            'status'     => 'stopped',
        ]);

        $this->assertEquals(1, $id1);
        $this->assertEquals(2, $id2);

        $server = DB::table('servers')->where('name', '=', 'node-alpha')->first();
        $this->assertNotNull($server);
        $this->assertEquals('10.0.0.1', $server['ip_address']);
        $this->assertEquals(4096, $server['ram_mb']);
    }

    public function testQueryBuilderWhereClauses(): void
    {
        DB::table('servers')->insert([
            ['name' => 'srv-1', 'ram_mb' => 2048, 'status' => 'running'],
            ['name' => 'srv-2', 'ram_mb' => 4096, 'status' => 'running'],
            ['name' => 'srv-3', 'ram_mb' => 8192, 'status' => 'stopped'],
            ['name' => 'srv-4', 'ram_mb' => 16384, 'status' => 'running'],
        ]);

        $inResults = DB::table('servers')->whereIn('ram_mb', [2048, 8192])->get();
        $this->assertCount(2, $inResults);

        $betweenResults = DB::table('servers')->whereBetween('ram_mb', [4000, 10000])->get();
        $this->assertCount(2, $betweenResults);

        $orResults = DB::table('servers')->where('status', '=', 'stopped')->orWhere('ram_mb', '>', 10000)->get();
        $this->assertCount(2, $orResults);
    }

    public function testQueryBuilderAggregates(): void
    {
        DB::table('servers')->insert([
            ['name' => 's1', 'ram_mb' => 1000, 'status' => 'running'],
            ['name' => 's2', 'ram_mb' => 3000, 'status' => 'running'],
            ['name' => 's3', 'ram_mb' => 6000, 'status' => 'running'],
        ]);

        $this->assertEquals(3, DB::table('servers')->count());
        $this->assertEquals(10000, DB::table('servers')->sum('ram_mb'));
        $this->assertEquals(1000, DB::table('servers')->min('ram_mb'));
        $this->assertEquals(6000, DB::table('servers')->max('ram_mb'));
    }

    public function testQueryBuilderUpdateAndDelete(): void
    {
        $id = DB::table('servers')->insertGetId([
            'name'   => 'target-server',
            'ram_mb' => 1024,
            'status' => 'provisioning',
        ]);

        $affected = DB::table('servers')->where('id', '=', $id)->update(['status' => 'active']);
        $this->assertEquals(1, $affected);

        $updated = DB::table('servers')->find($id);
        $this->assertEquals('active', $updated['status']);

        $deleted = DB::table('servers')->where('id', '=', $id)->delete();
        $this->assertEquals(1, $deleted);
        $this->assertNull(DB::table('servers')->find($id));
    }

    public function testQueryBuilderPagination(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            DB::table('servers')->insert([
                'name'   => "server-{$i}",
                'ram_mb' => 1024 * $i,
                'status' => 'running',
            ]);
        }

        $paginator = DB::table('servers')->orderBy('id', 'asc')->paginate(10, 2);

        $this->assertEquals(25, $paginator->total());
        $this->assertEquals(10, $paginator->perPage());
        $this->assertEquals(2, $paginator->currentPage());
        $this->assertEquals(3, $paginator->lastPage());
        $this->assertCount(10, $paginator->items());
        $this->assertEquals('server-11', $paginator->items()[0]['name']);
    }

    public function testTransactionsCommitAndRollback(): void
    {
        // Test commit
        DB::transaction(function () {
            DB::table('servers')->insert(['name' => 'committed-node', 'ram_mb' => 512, 'status' => 'running']);
        });

        $this->assertNotNull(DB::table('servers')->where('name', '=', 'committed-node')->first());

        // Test rollback on exception
        try {
            DB::transaction(function () {
                DB::table('servers')->insert(['name' => 'rolledback-node', 'ram_mb' => 512, 'status' => 'running']);
                throw new \RuntimeException('Intentional rollback');
            });
        } catch (\RuntimeException) {
        }

        $this->assertNull(DB::table('servers')->where('name', '=', 'rolledback-node')->first());
    }
}
