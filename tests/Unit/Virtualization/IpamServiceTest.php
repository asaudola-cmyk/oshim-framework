<?php
declare(strict_types=1);

namespace Tests\Unit\Virtualization;

use InvalidArgumentException;
use Oshim\Testing\TestCase;
use Oshim\Virtualization\Exceptions\NetworkException;
use Oshim\Virtualization\Network\IpamService;

class IpamServiceTest extends TestCase
{
    public function testStandardSubnetParsing(): void
    {
        $info = IpamService::parseCidr('10.42.0.0/24');

        $this->assertEquals('10.42.0.0/24', $info['cidr']);
        $this->assertEquals('10.42.0.0', $info['network_ip']);
        $this->assertEquals('255.255.255.0', $info['netmask_ip']);
        $this->assertEquals(24, $info['netmask_bits']);
        $this->assertEquals('10.42.0.1', $info['gateway_ip']);
        $this->assertEquals('10.42.0.2', $info['first_usable']);
        $this->assertEquals('10.42.0.254', $info['last_usable']);
        $this->assertEquals('10.42.0.255', $info['broadcast_ip']);
        $this->assertEquals(254, $info['total_hosts']);
    }

    public function testBoundarySubnetParsing(): void
    {
        // /32 (Single host)
        $sub32 = IpamService::parseCidr('198.51.100.42/32');
        $this->assertEquals('198.51.100.42', $sub32['network_ip']);
        $this->assertEquals('255.255.255.255', $sub32['netmask_ip']);
        $this->assertEquals(1, $sub32['total_hosts']);

        // /31 (RFC 3021 Point-to-Point)
        $sub31 = IpamService::parseCidr('10.0.0.0/31');
        $this->assertEquals('10.0.0.0', $sub31['network_ip']);
        $this->assertEquals('255.255.255.254', $sub31['netmask_ip']);
        $this->assertEquals(2, $sub31['total_hosts']);

        // /16 (Class B Equivalent)
        $sub16 = IpamService::parseCidr('172.16.0.0/16');
        $this->assertEquals(65534, $sub16['total_hosts']);
        $this->assertEquals('172.16.255.255', $sub16['broadcast_ip']);

        // Invalid CIDR
        $this->assertThrows(function () {
            IpamService::parseCidr('invalid-ip/24');
        }, InvalidArgumentException::class);

        $this->assertThrows(function () {
            IpamService::parseCidr('10.0.0.1/35');
        }, InvalidArgumentException::class);
    }

    public function testSubnetOverlapDetection(): void
    {
        // Overlapping: Supernet vs Subnet
        $this->assertTrue(IpamService::checkSubnetOverlap('10.42.0.0/16', '10.42.1.0/24'));
        $this->assertTrue(IpamService::checkSubnetOverlap('10.42.1.0/24', '10.42.0.0/16'));

        // Overlapping: Exact same subnet
        $this->assertTrue(IpamService::checkSubnetOverlap('192.168.1.0/24', '192.168.1.0/24'));

        // Disjoint: Different subnets
        $this->assertFalse(IpamService::checkSubnetOverlap('10.42.0.0/24', '10.43.0.0/24'));
        $this->assertFalse(IpamService::checkSubnetOverlap('192.168.1.0/24', '192.168.2.0/24'));
    }

    public function testDynamicIpAllocationAndRelease(): void
    {
        $ipam = new IpamService('10.42.0.0/24', '10.42.0.1');

        $ip1 = $ipam->allocateIp('inst_1');
        $this->assertEquals('10.42.0.2', $ip1);
        $this->assertTrue($ipam->isIpAllocated('10.42.0.2'));

        $ip2 = $ipam->allocateIp('inst_2');
        $this->assertEquals('10.42.0.3', $ip2);

        // Preferred IP allocation
        $ipPref = $ipam->allocateIp('inst_3', '10.42.0.50');
        $this->assertEquals('10.42.0.50', $ipPref);

        // Releasing IP
        $this->assertTrue($ipam->releaseIp('inst_1'));
        $this->assertFalse($ipam->isIpAllocated('10.42.0.2'));

        // Allocate again should reuse lowest available IP
        $ipNew = $ipam->allocateIp('inst_4');
        $this->assertEquals('10.42.0.2', $ipNew);
    }

    public function testSubnetExhaustionThrowsNetworkException(): void
    {
        // Slicing /30 has only 1 usable host (.2, since .0=net, .1=gateway, .3=broadcast)
        $ipam = new IpamService('10.42.0.0/30', '10.42.0.1');

        $ip1 = $ipam->allocateIp('inst_1');
        $this->assertEquals('10.42.0.2', $ip1);

        // Second allocation should fail
        $this->assertThrows(function () use ($ipam) {
            $ipam->allocateIp('inst_2');
        }, NetworkException::class);
    }

    public function testMacAddressGenerationCompliance(): void
    {
        $mac = IpamService::generateMacAddress('52:54:00');
        $this->assertMatchesRegularExpression('/^52:54:00:[0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2}$/i', $mac);

        // Verify unicast and locally administered bit characteristics
        $customMac = IpamService::generateMacAddress('02:00:00');
        $firstByte = hexdec(explode(':', $customMac)[0]);
        $this->assertEquals(0, $firstByte & 0x01); // Bit 0 is 0 (Unicast)
    }
}
