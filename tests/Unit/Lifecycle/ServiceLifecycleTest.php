<?php
declare(strict_types=1);

namespace Tests\Unit\Lifecycle;

use Oshim\Testing\TestCase;
use App\Models\Instance;
use App\Models\Invoice;
use Oshim\Lifecycle\ServiceLifecycleManager;
use Oshim\Lifecycle\ServiceState;
use Oshim\Virtualization\Driver\LxcDriver;
use Oshim\Virtualization\Driver\MockVirtualizationDriver;
use Oshim\Database\ConnectionManager;
use Oshim\Database\Schema\Schema;
use Oshim\Database\Schema\Blueprint;

class ServiceLifecycleTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        ConnectionManager::getInstance()->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ], 'default');

        Schema::dropIfExists('invoices');
        Schema::dropIfExists('instances');

        Schema::create('instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('hostname');
            $table->integer('cores')->default(1);
            $table->integer('memory_mb')->default(1024);
            $table->integer('disk_gb')->default(20);
            $table->string('os')->default('ubuntu-24.04');
            $table->string('ip_address')->nullable();
            $table->string('lifecycle_status')->default('pending');
            $table->string('next_due_date')->nullable();
            $table->string('suspended_at')->nullable();
            $table->string('terminated_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('instance_id')->nullable();
            $table->string('invoice_number');
            $table->integer('subtotal_cents')->default(0);
            $table->integer('tax_cents')->default(0);
            $table->integer('total_cents')->default(0);
            $table->string('currency')->default('USD');
            $table->string('status')->default('unpaid');
            $table->string('due_date')->nullable();
            $table->string('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function testInstanceAndInvoiceOrmModels(): void
    {
        $instance = Instance::create([
            'user_id'          => 101,
            'hostname'         => 'vps-sovereign-01',
            'cores'            => 4,
            'memory_mb'        => 8192,
            'disk_gb'          => 160,
            'os'               => 'debian-12',
            'ip_address'       => '10.42.0.50',
            'lifecycle_status' => ServiceState::STATE_ACTIVE,
            'next_due_date'    => date('Y-m-d', strtotime('+30 days')),
        ]);

        $this->assertNotNull($instance->id);
        $this->assertEquals(4, $instance->cores);
        $this->assertEquals(8192, $instance->memory_mb);
        $this->assertEquals('vps-sovereign-01', $instance->hostname);

        $invoice = Invoice::create([
            'user_id'        => 101,
            'instance_id'    => $instance->id,
            'invoice_number' => 'INV-2026-00001',
            'subtotal_cents' => 2000,
            'tax_cents'      => 100,
            'total_cents'    => 2100,
            'currency'       => 'USD',
            'status'         => 'unpaid',
            'due_date'       => date('Y-m-d', strtotime('+7 days')),
        ]);

        $this->assertNotNull($invoice->id);
        $this->assertEquals('unpaid', $invoice->status);
        $this->assertNull($invoice->paid_at);

        // Test Mark As Paid
        $this->assertTrue($invoice->markAsPaid());
        $this->assertEquals('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        // Test Relationships
        $invoices = $instance->invoices;
        $this->assertCount(1, $invoices);
        $this->assertEquals('INV-2026-00001', $invoices[0]->invoice_number);

        $linkedInstance = $invoice->instance;
        $this->assertNotNull($linkedInstance);
        $this->assertEquals('vps-sovereign-01', $linkedInstance->hostname);

        // Test Soft Deletes
        $this->assertFalse($instance->trashed());
        $instance->delete();
        $this->assertTrue($instance->trashed());
        $this->assertNull(Instance::find($instance->id));
        $this->assertNotNull(Instance::withTrashed()->where('id', $instance->id)->first());
    }

    public function testLxcDriverInitializationAndName(): void
    {
        $driver = new LxcDriver();
        $this->assertEquals('LxcDriver', $driver->getDriverName());
        $this->assertTrue($driver instanceof \Oshim\Virtualization\Driver\NativeLinuxDriver);
        $this->assertTrue($driver instanceof \Oshim\Virtualization\Driver\VirtualizationDriverInterface);
    }

    public function testServiceLifecycleTransitions(): void
    {
        $manager = new ServiceLifecycleManager(null, null, null, ServiceState::STATE_PENDING);
        $this->assertEquals(ServiceState::STATE_PENDING, $manager->getState());

        $state = $manager->transition('activate');
        $this->assertEquals(ServiceState::STATE_ACTIVE, $state);

        $state = $manager->transition('pass_due_date');
        $this->assertEquals(ServiceState::STATE_OVERDUE, $state);

        $state = $manager->transition('expire_grace_period');
        $this->assertEquals(ServiceState::STATE_SUSPENDED, $state);

        $state = $manager->transition('unsuspend');
        $this->assertEquals(ServiceState::STATE_ACTIVE, $state);

        $state = $manager->transition('terminate');
        $this->assertEquals(ServiceState::STATE_TERMINATED, $state);

        $history = $manager->getHistory();
        $this->assertCount(6, $history);
    }

    public function testServiceLifecycleDailyCheckWithOrmInstances(): void
    {
        $today = date('Y-m-d');

        // T-5 days: Renewal invoice generated
        Instance::create([
            'user_id'          => 1,
            'hostname'         => 'inst-t5',
            'lifecycle_status' => ServiceState::STATE_ACTIVE,
            'next_due_date'    => date('Y-m-d', strtotime('+5 days')),
        ]);

        // T-2 days: Reminder sent
        Instance::create([
            'user_id'          => 2,
            'hostname'         => 'inst-t2',
            'lifecycle_status' => ServiceState::STATE_ACTIVE,
            'next_due_date'    => date('Y-m-d', strtotime('+2 days')),
        ]);

        // T-2 days past due: Grace period
        Instance::create([
            'user_id'          => 3,
            'hostname'         => 'inst-t-2',
            'lifecycle_status' => ServiceState::STATE_ACTIVE,
            'next_due_date'    => date('Y-m-d', strtotime('-2 days')),
        ]);

        // T-10 days past due: Auto suspend
        Instance::create([
            'user_id'          => 4,
            'hostname'         => 'inst-t-10',
            'lifecycle_status' => ServiceState::STATE_OVERDUE,
            'next_due_date'    => date('Y-m-d', strtotime('-10 days')),
        ]);

        // T-16 days past due: Auto terminate
        Instance::create([
            'user_id'          => 5,
            'hostname'         => 'inst-t-16',
            'lifecycle_status' => ServiceState::STATE_SUSPENDED,
            'next_due_date'    => date('Y-m-d', strtotime('-16 days')),
        ]);

        $manager = new ServiceLifecycleManager();
        $stats = $manager->runDailyLifecycleCheck();

        $this->assertEquals(1, $stats['renewal_invoices_generated']);
        $this->assertEquals(1, $stats['reminders_sent']);
        $this->assertEquals(1, $stats['grace_periods_started']);
        $this->assertEquals(1, $stats['auto_suspended']);
        $this->assertEquals(1, $stats['auto_terminated']);

        // Check updated states in DB
        $inst3 = Instance::where('hostname', 'inst-t-2')->first();
        $this->assertEquals(ServiceState::STATE_OVERDUE, $inst3->lifecycle_status);

        $inst4 = Instance::where('hostname', 'inst-t-10')->first();
        $this->assertEquals(ServiceState::STATE_SUSPENDED, $inst4->lifecycle_status);
        $this->assertNotNull($inst4->suspended_at);

        $inst5 = Instance::where('hostname', 'inst-t-16')->first();
        $this->assertEquals(ServiceState::STATE_TERMINATED, $inst5->lifecycle_status);
        $this->assertNotNull($inst5->terminated_at);
    }

    public function testHandleInvoicePaidAndProvisioning(): void
    {
        $mockVirt = new MockVirtualizationDriver();
        $manager = new ServiceLifecycleManager($mockVirt, null, null, ServiceState::STATE_PENDING);

        $invoice = Invoice::create([
            'user_id'        => 10,
            'invoice_number' => 'INV-2026-TEST',
            'status'         => 'unpaid',
        ]);

        $manager->handleInvoicePaid($invoice);
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(ServiceState::STATE_ACTIVE, $manager->getState());

        $instanceId = $manager->provisionService([
            'type'   => 'lxc',
            'id'     => 'lxc-test-node',
            'name'   => 'lxc-test-node',
            'image'  => 'alpine:latest',
            'vcpu'   => 2,
            'memory' => 512 * 1024 * 1024,
            'disk'   => 10 * 1024 * 1024 * 1024,
        ]);

        $this->assertEquals('lxc-test-node', $instanceId);
    }
}
