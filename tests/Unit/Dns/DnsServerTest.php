<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Dns;

use Oshim\Dns\Records\ResourceRecord;
use Oshim\Dns\Server\DnsServer;
use Oshim\Dns\Server\DnsServerConfig;
use Oshim\Dns\Zone\MemoryZoneRepository;
use Oshim\Dns\Zone\Zone;
use Oshim\Tests\Harness\TestCase;
use Oshim\Tests\Harness\MockDnsClient;
use Oshim\Tests\Harness\DnsWireResponse;

class DnsServerTest extends TestCase
{
    private MemoryZoneRepository $repo;
    private DnsServer $server;
    private int $port;
    private MockDnsClient $dnsClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new MemoryZoneRepository();
        $this->dnsClient = new MockDnsClient();

        $zone = new Zone('servertest.cloud', 3600, 1, [
            ResourceRecord::soa('servertest.cloud', 'ns1.servertest.cloud', 'hostmaster.servertest.cloud', 1),
            ResourceRecord::a('servertest.cloud', '198.51.100.42'),
            ResourceRecord::aaaa('servertest.cloud', '2001:db8:42::1'),
        ]);
        $this->repo->saveZone($zone);

        // Pick dynamic unprivileged high port
        $this->port = random_int(52000, 58000);
        $config = new DnsServerConfig('127.0.0.1', $this->port);
        $this->server = new DnsServer($this->repo, $config);
    }

    protected function tearDown(): void
    {
        $this->server->stop();
        parent::tearDown();
    }

    public function testUdpQueryResponseCycle(): void
    {
        $this->server->listen();
        $this->assertTrue($this->server->isRunning());

        // Send UDP query via client socket
        $queryWire = $this->dnsClient->buildQueryPacket('servertest.cloud', 'A', 7001);

        $clientSock = stream_socket_client("udp://127.0.0.1:{$this->port}", $errno, $errstr, 1.0);
        $this->assertNotNull($clientSock);
        stream_set_timeout($clientSock, 1);
        fwrite($clientSock, $queryWire);

        // Server tick processes UDP datagram
        $this->server->tick(20000);

        // Client reads response
        $respWire = fread($clientSock, 4096);
        fclose($clientSock);

        $this->assertNotEmpty($respWire);
        $parsed = DnsWireResponse::parse($respWire);
        $parsed->assertNoError();
        $parsed->assertAuthoritative();
        $parsed->assertHasRecord('A', '198.51.100.42');
    }

    public function testTcpTwoByteFramedQueryResponseCycle(): void
    {
        $this->server->listen();

        // Connect TCP client
        $clientSock = stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $errstr, 1.0);
        $this->assertNotNull($clientSock);
        stream_set_blocking($clientSock, false);

        // Server tick accepts connection
        $this->server->tick(20000);

        // Client sends 2-byte framed query
        $queryWire = $this->dnsClient->buildQueryPacket('servertest.cloud', 'AAAA', 7002);
        $framed = pack('n', strlen($queryWire)) . $queryWire;
        fwrite($clientSock, $framed);

        // Server tick reads data and writes framed response
        $this->server->tick(20000);

        // Read 2-byte prefix + response
        stream_set_blocking($clientSock, true);
        stream_set_timeout($clientSock, 1);
        $hdr = fread($clientSock, 2);
        $this->assertSame(2, strlen($hdr));
        $respLen = unpack('n', $hdr)[1];

        $respPayload = fread($clientSock, $respLen);
        fclose($clientSock);

        $parsed = DnsWireResponse::parse($respPayload);
        $parsed->assertNoError();
        $parsed->assertAuthoritative();
        $parsed->assertHasRecord('AAAA', '2001:db8:42::1');
    }

    public function testMalformedUdpPacketHandlingDoesNotCrashServer(): void
    {
        $this->server->listen();

        // Send corrupt 5-byte datagram
        $clientSock = stream_socket_client("udp://127.0.0.1:{$this->port}", $errno, $errstr, 1.0);
        fwrite($clientSock, "\x01\x02\x03\x04\x05");

        // Server tick handles packet safely
        $this->server->tick(20000);

        $resp = fread($clientSock, 4096);
        fclose($clientSock);

        // Server responded with FORMERR packet
        $this->assertNotEmpty($resp);
        $parsed = DnsWireResponse::parse($resp);
        $this->assertSame(1, $parsed->getRCode()); // FORMERR
    }
}
