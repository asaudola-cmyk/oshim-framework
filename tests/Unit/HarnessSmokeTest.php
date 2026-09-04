<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit;

use Oshim\Tests\Harness\TestCase;
use Oshim\Tests\Harness\Assert;
use Oshim\Tests\Harness\AssertionException;
use Oshim\Tests\Harness\TestSkippedException;
use Oshim\Tests\Harness\TestResult;
use Oshim\Tests\Harness\TestSuite;
use Oshim\Tests\Harness\MockEppRegistry;
use Oshim\Tests\Harness\MockDnsClient;
use Oshim\Tests\Harness\DnsWireResponse;
use Oshim\Tests\Harness\VirtualizationMockDriver;
use Oshim\Tests\Harness\DatabaseSandbox;
use Oshim\Tests\Harness\HttpTestClient;
use RuntimeException;
use InvalidArgumentException;

/**
 * Unit smoke test verifying all harness components, assertions, mock drivers, and lifecycle hooks.
 */
class HarnessSmokeTest extends TestCase
{
    private static bool $classSetUpExecuted = false;
    private bool $instanceSetUpExecuted = false;

    public static function setUpBeforeClass(): void
    {
        self::$classSetUpExecuted = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->instanceSetUpExecuted = true;
    }

    public function testLifecycleHooksExecuted(): void
    {
        $this->assertTrue(self::$classSetUpExecuted, "Class setUpBeforeClass hook should be executed");
        $this->assertTrue($this->instanceSetUpExecuted, "Instance setUp hook should be executed");
    }

    public function testAssertPrimitives(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
        $this->assertEquals(42, 42);
        $this->assertEquals('42', 42);
        $this->assertNotEquals(42, 43);
        $this->assertSame('hello', 'hello');
        $this->assertNotSame(42, '42');
        $this->assertNull(null);
        $this->assertNotNull(0);
        $this->assertNotNull('');
        $this->assertEmpty([]);
        $this->assertEmpty('');
        $this->assertEmpty(0);
        $this->assertNotEmpty([1]);
        $this->assertNotEmpty('content');
    }

    public function testStringAssertions(): void
    {
        $haystack = "OSHIM Cloud Native Virtualization and DNS Engine";
        $this->assertStringContains("Cloud Native", $haystack);
        $this->assertStringNotContains("Kubernetes", $haystack);
        $this->assertStringStartsWith("OSHIM", $haystack);
        $this->assertStringEndsWith("Engine", $haystack);
        $this->assertMatchesRegex('/^OSHIM\s+Cloud/', $haystack);
        $this->assertDoesNotMatchRegex('/Docker/', $haystack);
    }

    public function testArrayAndCountAssertions(): void
    {
        $items = ['alpha' => 1, 'beta' => 2, 'gamma' => ['nested' => 'val']];
        $this->assertCount(3, $items);
        $this->assertArrayHasKey('alpha', $items);
        $this->assertArrayHasKey('gamma', $items);
        $this->assertArrayNotHasKey('delta', $items);
        $this->assertArraySubset(['alpha' => 1, 'gamma' => ['nested' => 'val']], $items);
    }

    public function testComparisonAssertions(): void
    {
        $this->assertGreaterThan(10, 20);
        $this->assertGreaterThanOrEqual(20, 20);
        $this->assertGreaterThanOrEqual(10, 20);
        $this->assertLessThan(20, 10);
        $this->assertLessThanOrEqual(10, 10);
        $this->assertLessThanOrEqual(20, 10);
    }

    public function testJsonAssertions(): void
    {
        $json = '{"name":"OSHIM Cloud","version":"1.0.0","features":["epp","dns","vps"]}';
        $this->assertJson($json);
        $this->assertJsonEquals(['name' => 'OSHIM Cloud', 'version' => '1.0.0', 'features' => ['epp', 'dns', 'vps']], $json);
        $this->assertJsonContains(['name' => 'OSHIM Cloud', 'features' => ['epp', 'dns', 'vps']], $json);
    }

    public function testExceptionAssertions(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function () {
            throw new InvalidArgumentException("Invalid config key");
        }, "Invalid config");

        $result = $this->assertDoesNotThrow(function () {
            return 100 * 2;
        });
        $this->assertSame(200, $result);
    }

    public function testAssertionFailureDiffGeneration(): void
    {
        $caught = false;
        try {
            Assert::assertEquals(['a' => 1, 'b' => 2], ['a' => 1, 'b' => 3]);
        } catch (AssertionException $e) {
            $caught = true;
            $diff = $e->getDiff();
            $this->assertNotNull($diff);
            $this->assertStringContains('--- Expected', $diff);
            $this->assertStringContains('+++ Actual', $diff);
        }
        $this->assertTrue($caught, "AssertionException should be caught with generated diff");
    }

    public function testDatabaseSandboxInMemory(): void
    {
        $sandbox = $this->db();
        $this->assertNotNull($sandbox->getPdo());

        // Create test table and execute queries
        $sandbox->exec("CREATE TABLE IF NOT EXISTS smoke_test_items (id INTEGER PRIMARY KEY, name VARCHAR(100), val INTEGER)");
        $sandbox->exec("INSERT INTO smoke_test_items (id, name, val) VALUES (1, 'item-one', 100)");

        $sandbox->assertHas('smoke_test_items', ['id' => 1, 'name' => 'item-one']);
        $sandbox->assertMissing('smoke_test_items', ['id' => 999]);
        $sandbox->assertCount('smoke_test_items', 1);

        $row = $sandbox->fetchOne('smoke_test_items', ['name' => 'item-one']);
        $this->assertNotNull($row);
        $this->assertEquals(100, $row['val']);

        // Test transaction rollback
        $sandbox->beginTransaction();
        $sandbox->exec("INSERT INTO smoke_test_items (id, name, val) VALUES (2, 'item-two', 200)");
        $sandbox->assertCount('smoke_test_items', 2);
        $sandbox->rollBack();
        $sandbox->assertCount('smoke_test_items', 1);

        // Test baseline seeding
        $sandbox->seedBaseline();
        $sandbox->assertHas('users', ['email' => 'superadmin@oshim.cloud', 'role' => 'superadmin']);
        $sandbox->assertHas('users', ['email' => 'client@oshim.cloud', 'role' => 'client']);
    }

    public function testMockEppRegistryProtocolFramingAndCommands(): void
    {
        $registry = $this->createMockEppRegistry();

        // 1. Framing and Greeting
        $greetingXml = $registry->generateGreeting();
        $this->assertStringContains('<greeting>', $greetingXml);
        $this->assertStringContains('<svID>OSHIM-MOCK-EPP-REGISTRY-v1.0</svID>', $greetingXml);

        $framedGreeting = $registry->frameXml($greetingXml);
        $this->assertGreaterThan(4, strlen($framedGreeting));
        $unframed = $registry->unframeXml($framedGreeting);
        $this->assertSame($greetingXml, $unframed);

        // 2. Login
        $loginXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <login>
      <clID>OSHIM_REGISTRAR</clID>
      <pw>ValidPassword123</pw>
    </login>
    <clTRID>TRID-LOGIN-001</clTRID>
  </command>
</epp>
XML;
        $framedResponse = $registry->dispatch($registry->frameXml($loginXml));
        $respXml = $registry->unframeXml($framedResponse);
        $this->assertStringContains('<result code="1000">', $respXml);

        // 3. Domain Check (Available)
        $checkXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <check>
      <domain:check xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name>mycompany.cloud</domain:name>
      </domain:check>
    </check>
    <clTRID>TRID-CHK-001</clTRID>
  </command>
</epp>
XML;
        $respXml = $registry->unframeXml($registry->dispatch($registry->frameXml($checkXml)));
        $this->assertStringContains('<domain:name avail="1">mycompany.cloud</domain:name>', $respXml);

        // 4. Domain Create
        $createXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <create>
      <domain:create xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name>mycompany.cloud</domain:name>
        <domain:period unit="y">2</domain:period>
        <domain:ns>
          <domain:hostObj>ns1.oshim.cloud</domain:hostObj>
          <domain:hostObj>ns2.oshim.cloud</domain:hostObj>
        </domain:ns>
        <domain:registrant>REG-001</domain:registrant>
        <domain:authInfo>
          <domain:pw>SecretAuthPw123!</domain:pw>
        </domain:authInfo>
      </domain:create>
    </create>
    <clTRID>TRID-CRE-001</clTRID>
  </command>
</epp>
XML;
        $respXml = $registry->unframeXml($registry->dispatch($registry->frameXml($createXml)));
        $this->assertStringContains('<result code="1000">', $respXml);
        $this->assertStringContains('<domain:creData', $respXml);

        // Check domain in registry
        $domainData = $registry->getDomain('mycompany.cloud');
        $this->assertNotNull($domainData);
        $this->assertSame(2, $domainData['period']);
        $this->assertCount(2, $domainData['nameservers']);

        // Check availability again (now taken)
        $respXml = $registry->unframeXml($registry->dispatch($registry->frameXml($checkXml)));
        $this->assertStringContains('<domain:name avail="0">mycompany.cloud</domain:name>', $respXml);

        // 5. Host Create with Glue Records
        $hostCreateXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <create>
      <host:create xmlns:host="urn:ietf:params:xml:ns:host-1.0">
        <host:name>ns1.mycompany.cloud</host:name>
        <host:addr ip="v4">192.0.2.53</host:addr>
        <host:addr ip="v6">2001:db8::53</host:addr>
      </host:create>
    </create>
    <clTRID>TRID-HOST-001</clTRID>
  </command>
</epp>
XML;
        $respXml = $registry->unframeXml($registry->dispatch($registry->frameXml($hostCreateXml)));
        $this->assertStringContains('<result code="1000">', $respXml);
        $this->assertStringContains('<host:creData', $respXml);
        $hostData = $registry->getHost('ns1.mycompany.cloud');
        $this->assertNotNull($hostData);
        $this->assertSame(['192.0.2.53'], $hostData['ipv4']);
        $this->assertSame(['2001:db8::53'], $hostData['ipv6']);

        // 6. Fault Injection
        $registry->injectFailure('renew', 2304, "Object status prohibits operation");
        $renewXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <renew>
      <domain:renew xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name>mycompany.cloud</domain:name>
        <domain:curExpDate>2028-08-29</domain:curExpDate>
      </domain:renew>
    </renew>
    <clTRID>TRID-REN-001</clTRID>
  </command>
</epp>
XML;
        $respXml = $registry->unframeXml($registry->dispatch($registry->frameXml($renewXml)));
        $this->assertStringContains('<result code="2304">', $respXml);
        $this->assertStringContains('Object status prohibits operation', $respXml);
    }

    public function testMockDnsClientWirePacketCodec(): void
    {
        $dns = $this->createMockDnsClient();

        // 1. Domain label encoding & decoding
        $domain = "dns.example.com";
        $encoded = MockDnsClient::encodeDomainName($domain);
        $this->assertSame("\x03dns\x07example\x03com\x00", $encoded);
        $offset = 0;
        $decoded = MockDnsClient::decodeDomainName($encoded, $offset);
        $this->assertSame($domain, $decoded);

        // 2. Build Query Packet
        $queryWire = $dns->buildQueryPacket("host.example.com", 'A', 12345);
        $this->assertGreaterThan(12, strlen($queryWire));

        // 3. Build & Parse Response Packet for All 9 Record Types
        $records = [
            ['value' => '198.51.100.1'],
            ['value' => '198.51.100.2'],
        ];
        $responseWire = $dns->buildResponsePacket(12345, "host.example.com", 'A', $records, true, 0, 300);
        $parsed = DnsWireResponse::parse($responseWire);

        $parsed->assertNoError();
        $parsed->assertAuthoritative();
        $parsed->assertRecordCount(2, 'A');
        $parsed->assertHasRecord('A', '198.51.100.1');
        $parsed->assertHasRecord('A', '198.51.100.2');

        // Test AAAA
        $wireAaaa = $dns->buildResponsePacket(12346, "v6.example.com", 'AAAA', [['value' => '2001:db8::10']], true);
        $parsedAaaa = DnsWireResponse::parse($wireAaaa);
        $parsedAaaa->assertNoError();
        $parsedAaaa->assertHasRecord('AAAA', '2001:db8::10');

        // Test MX
        $wireMx = $dns->buildResponsePacket(12347, "example.com", 'MX', [['preference' => 10, 'exchange' => 'mail.example.com']], true);
        $parsedMx = DnsWireResponse::parse($wireMx);
        $parsedMx->assertNoError();
        $parsedMx->assertHasRecord('MX', 'mail.example.com');

        // Test TXT
        $wireTxt = $dns->buildResponsePacket(12348, "example.com", 'TXT', [['value' => 'v=spf1 include:_spf.example.com ~all']], true);
        $parsedTxt = DnsWireResponse::parse($wireTxt);
        $parsedTxt->assertNoError();
        $parsedTxt->assertHasRecord('TXT', 'v=spf1 include:_spf.example.com ~all');

        // Test CAA
        $wireCaa = $dns->buildResponsePacket(12349, "example.com", 'CAA', [['flags' => 0, 'tag' => 'issue', 'value' => 'letsencrypt.org']], true);
        $parsedCaa = DnsWireResponse::parse($wireCaa);
        $parsedCaa->assertNoError();
        $parsedCaa->assertHasRecord('CAA', 'letsencrypt.org');
    }

    public function testVirtualizationMockDriverLifecycleAndStats(): void
    {
        $driver = $this->createVirtualizationDriver();

        // 1. Create Instance
        $spec = [
            'name' => 'prod-web-01',
            'vcpu' => 4,
            'ram_mb' => 8192,
            'disk_gb' => 100,
            'os' => 'debian-12',
        ];
        $instanceId = $driver->createInstance($spec);
        $this->assertStringStartsWith('vm-', $instanceId);

        $inst = $driver->getInstance($instanceId);
        $this->assertNotNull($inst);
        $this->assertSame('STOPPED', $inst['state']);
        $this->assertSame(4, $inst['vcpu']);
        $this->assertSame(8192, $inst['ram_mb']);

        // Check cgroups
        $cgroups = $driver->getCgroupLimits($instanceId);
        $this->assertSame('400000 100000', $cgroups['cpu.max']);
        $this->assertSame(8192 * 1024 * 1024, $cgroups['memory.max']);

        // 2. Start Instance
        $this->assertTrue($driver->startInstance($instanceId));
        $inst = $driver->getInstance($instanceId);
        $this->assertSame('RUNNING', $inst['state']);

        // 3. Telemetry Stats
        $stats = $driver->getInstanceStats($instanceId);
        $this->assertSame('RUNNING', $stats['state']);
        $this->assertGreaterThan(0.0, $stats['cpu_usage_pct']);
        $this->assertGreaterThan(0, $stats['ram_used_bytes']);
        $this->assertSame(8192 * 1024 * 1024, $stats['ram_total_bytes']);

        // 4. Snapshot & Rollback
        $snapId = $driver->createSnapshot($instanceId, 'before-upgrade');
        $this->assertStringStartsWith('snap-', $snapId);
        $this->assertCount(1, $driver->listSnapshots($instanceId));

        $this->assertTrue($driver->stopInstance($instanceId));
        $this->assertSame('STOPPED', $driver->getInstance($instanceId)['state']);

        $this->assertTrue($driver->rollbackSnapshot($instanceId, $snapId));

        // 5. Suspend & Resume
        $driver->startInstance($instanceId);
        $this->assertTrue($driver->suspendInstance($instanceId));
        $this->assertSame('SUSPENDED', $driver->getInstance($instanceId)['state']);
        $this->assertTrue($driver->resumeInstance($instanceId));
        $this->assertSame('RUNNING', $driver->getInstance($instanceId)['state']);

        // 6. Destroy
        $this->assertTrue($driver->destroyInstance($instanceId));
        $this->assertNull($driver->getInstance($instanceId));

        // 7. Fault Injection
        $driver->injectFault('createInstance', 'No capacity on node');
        $this->assertThrows(RuntimeException::class, function () use ($driver, $spec) {
            $driver->createInstance($spec);
        }, 'No capacity on node');
    }

    public function testHttpTestClientAndResponseAssertions(): void
    {
        $client = $this->http();

        // 1. JSON Request
        $user = ['id' => 42, 'email' => 'admin@oshim.cloud', 'role' => 'admin'];
        $client = $client->actingAs($user, 'admin')
                         ->withHeaders(['Accept' => 'application/json', 'X-Custom-Header' => 'TestVal'])
                         ->withCookies(['session_id' => 'sess_abc123']);

        $response = $client->get('/api/status');
        $response->assertOk();
        $response->assertStatus(200);
        $response->assertContentType('application/json');
        $response->assertJson();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('user.email', 'admin@oshim.cloud');
        $response->assertCookie('session_id', 'sess_abc123');

        $this->assertSame('success', $response->json('status'));
        $this->assertSame('admin@oshim.cloud', $response->json('user.email'));

        // 2. HTML Request
        $htmlClient = (new HttpTestClient())->withHeaders(['Accept' => 'text/html']);
        $htmlResponse = $htmlClient->get('/');
        $htmlResponse->assertOk();
        $htmlResponse->assertSee('OSHIM Cloud');
        $htmlResponse->assertDontSee('Fatal Error');

        // 3. SSE Stream Parser
        $sseEvents = [];
        $client->sseStream('/client/telemetry/stream', function ($event, $data, $id, $stop) use (&$sseEvents) {
            $sseEvents[] = ['event' => $event, 'data' => $data];
            if (count($sseEvents) >= 2) {
                $stop();
            }
        }, 5);

        $this->assertCount(2, $sseEvents);
        $this->assertSame('ping', $sseEvents[0]['event']);
        $this->assertSame('ok', $sseEvents[0]['data']['status']);
        $this->assertSame('telemetry', $sseEvents[1]['event']);
    }

    public function testTestResultAndTestSuiteDiscovery(): void
    {
        // 1. TestResult recording
        $result = new TestResult(self::class, 'testDummy');
        $result->markPassed();
        $result->setMetrics(0.0025, 4, 1024 * 512);

        $this->assertTrue($result->isPassed());
        $this->assertFalse($result->isFailed());
        $this->assertSame(self::class, $result->getClassName());
        $this->assertSame('testDummy', $result->getMethodName());
        $this->assertSame(4, $result->getAssertions());
        $this->assertEquals(0.0025, $result->getDuration());

        // 2. TestSuite discovery
        $suite = new TestSuite();
        $suite->discover(['path' => __FILE__]);
        $this->assertFalse($suite->isEmpty());
        $this->assertGreaterThanOrEqual(10, $suite->count());
        $classes = $suite->getTestClasses();
        $this->assertArrayHasKey(self::class, $classes);
        $this->assertContains('testLifecycleHooksExecuted', $classes[self::class]);
    }
}
