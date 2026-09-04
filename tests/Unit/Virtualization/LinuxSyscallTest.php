<?php
declare(strict_types=1);

namespace Tests\Unit\Virtualization;

use Oshim\Testing\TestCase;
use Oshim\Virtualization\Exceptions\SyscallException;
use Oshim\Virtualization\Syscall\LinuxConstants;
use Oshim\Virtualization\Syscall\LinuxSyscall;
use Oshim\Virtualization\Syscall\MockSyscall;

class LinuxSyscallTest extends TestCase
{
    public function testNamespaceCloneConstantsAndComposition(): void
    {
        $this->assertEquals(0x00020000, LinuxConstants::CLONE_NEWNS);
        $this->assertEquals(0x02000000, LinuxConstants::CLONE_NEWCGROUP);
        $this->assertEquals(0x04000000, LinuxConstants::CLONE_NEWUTS);
        $this->assertEquals(0x08000000, LinuxConstants::CLONE_NEWIPC);
        $this->assertEquals(0x10000000, LinuxConstants::CLONE_NEWUSER);
        $this->assertEquals(0x20000000, LinuxConstants::CLONE_NEWPID);
        $this->assertEquals(0x40000000, LinuxConstants::CLONE_NEWNET);

        $flags = LinuxConstants::buildNamespaceFlags(['mount', 'pid', 'net', 'uts', 'ipc', 'cgroup', 'user']);
        $expected = LinuxConstants::CLONE_NEWNS
            | LinuxConstants::CLONE_NEWPID
            | LinuxConstants::CLONE_NEWNET
            | LinuxConstants::CLONE_NEWUTS
            | LinuxConstants::CLONE_NEWIPC
            | LinuxConstants::CLONE_NEWCGROUP
            | LinuxConstants::CLONE_NEWUSER;

        $this->assertEquals($expected, $flags);
        $this->assertTrue(($flags & LinuxConstants::CLONE_NEWPID) !== 0);
        $this->assertTrue(($flags & LinuxConstants::CLONE_NEWNET) !== 0);

        // Unknown namespace name should be skipped
        $partial = LinuxConstants::buildNamespaceFlags(['mount', 'invalid_name']);
        $this->assertEquals(LinuxConstants::CLONE_NEWNS, $partial);
    }

    public function testMountAndIoctlConstants(): void
    {
        $this->assertEquals(1, LinuxConstants::MS_RDONLY);
        $this->assertEquals(2, LinuxConstants::MS_NOSUID);
        $this->assertEquals(4, LinuxConstants::MS_NODEV);
        $this->assertEquals(8, LinuxConstants::MS_NOEXEC);
        $this->assertEquals(32, LinuxConstants::MS_REMOUNT);
        $this->assertEquals(4096, LinuxConstants::MS_BIND);
        $this->assertEquals(16384, LinuxConstants::MS_REC);
        $this->assertEquals(262144, LinuxConstants::MS_PRIVATE);
        $this->assertEquals(524288, LinuxConstants::MS_SLAVE);
        $this->assertEquals(1048576, LinuxConstants::MS_SHARED);
        $this->assertEquals(2, LinuxConstants::MNT_DETACH);

        $this->assertEquals(0x400454ca, LinuxConstants::TUNSETIFF);
        $this->assertEquals(0x0001, LinuxConstants::IFF_TUN);
        $this->assertEquals(0x0002, LinuxConstants::IFF_TAP);
        $this->assertEquals(0x1000, LinuxConstants::IFF_NO_PI);

        $this->assertEquals(0x89a0, LinuxConstants::SIOCBRADDBR);
        $this->assertEquals(0x89a1, LinuxConstants::SIOCBRDELBR);
        $this->assertEquals(0x89a2, LinuxConstants::SIOCBRADDIF);
        $this->assertEquals(0x89a3, LinuxConstants::SIOCBRDELIF);
    }

    public function testSyscallPivotRootArchitectureResolution(): void
    {
        $pivotRootSyscall = LinuxConstants::getSyscallPivotRoot();
        $this->assertTrue($pivotRootSyscall === LinuxConstants::SYS_X86_64_PIVOT_ROOT || $pivotRootSyscall === LinuxConstants::SYS_AARCH64_PIVOT_ROOT);
        $this->assertEquals(155, LinuxConstants::SYS_X86_64_PIVOT_ROOT);
        $this->assertEquals(217, LinuxConstants::SYS_AARCH64_PIVOT_ROOT);
    }

    public function testMockSyscallExecutionAndCallTracking(): void
    {
        $mock = new MockSyscall();
        $mock->reset();

        $this->assertEquals(0, $mock->unshare(LinuxConstants::CLONE_NEWPID | LinuxConstants::CLONE_NEWNET));
        $this->assertEquals(0, $mock->setns(12, LinuxConstants::CLONE_NEWNET));
        $this->assertEquals(0, $mock->mount('overlay', '/merged', 'overlay', 0, 'lowerdir=l,upperdir=u,workdir=w'));
        $this->assertEquals(0, $mock->setHostname('vps-node-01'));
        $this->assertEquals(0, $mock->chdir('/var/log'));
        $this->assertEquals(0, $mock->chroot('/merged'));
        $this->assertEquals(0, $mock->pivotRoot('/merged', '/merged/.oldroot'));
        $this->assertEquals(0, $mock->umount2('/merged/.oldroot', LinuxConstants::MNT_DETACH));
        $this->assertEquals(0, $mock->ioctl(5, LinuxConstants::TUNSETIFF));
        $this->assertEquals(0, $mock->syncfs(5));
        $fd = $mock->open('/tmp/test.txt', 2);
        $this->assertTrue($fd >= 0);
        $this->assertEquals(0, $mock->close($fd));
        $this->assertEquals(10001, $mock->getPid());
        $this->assertEquals(0, $mock->getEuid());

        $calls = $mock->getCalls();
        $this->assertTrue(count($calls) >= 10);
        $this->assertEquals('unshare', $calls[0]['syscall']);
        $this->assertEquals('setns', $calls[1]['syscall']);
        $this->assertEquals('mount', $calls[2]['syscall']);
    }

    public function testMockSyscallForcedFailureAndErrno(): void
    {
        $mock = new MockSyscall();
        $mock->forceResult('unshare', -1, 1 /* EPERM */);

        $res = $mock->unshare(LinuxConstants::CLONE_NEWNS);
        $this->assertEquals(-1, $res);
        $this->assertEquals(1, $mock->getLastError());
        $this->assertEquals('Operation not permitted', $mock->getErrorString(1));

        // Relative path for pivot_root returns -1
        $resPivot = $mock->pivotRoot('relative/path', 'old');
        $this->assertEquals(-1, $resPivot);
        $this->assertEquals(22, $mock->getLastError());
    }

    public function testLinuxSyscallDiagnosticResolution(): void
    {
        $diagEperm = LinuxSyscall::resolveDiagnosticMessage(1, 'unshare');
        $this->assertStringContainsString('Operation not permitted', $diagEperm);
        $this->assertStringContainsString('CAP_SYS_ADMIN', $diagEperm);

        $diagEnoent = LinuxSyscall::resolveDiagnosticMessage(2, 'mount');
        $this->assertStringContainsString('No such file or directory', $diagEnoent);

        $diagEsrch = LinuxSyscall::resolveDiagnosticMessage(3, 'kill');
        $this->assertStringContainsString('No such process', $diagEsrch);

        $diagEnomem = LinuxSyscall::resolveDiagnosticMessage(12, 'clone');
        $this->assertStringContainsString('Cannot allocate memory', $diagEnomem);

        $diagEacces = LinuxSyscall::resolveDiagnosticMessage(13, 'open');
        $this->assertStringContainsString('Permission denied', $diagEacces);

        $diagEbusy = LinuxSyscall::resolveDiagnosticMessage(16, 'umount');
        $this->assertStringContainsString('Device or resource busy', $diagEbusy);

        $diagEinval = LinuxSyscall::resolveDiagnosticMessage(22, 'mount');
        $this->assertStringContainsString('Invalid argument', $diagEinval);

        $diagEmfile = LinuxSyscall::resolveDiagnosticMessage(24, 'open');
        $this->assertStringContainsString('Too many open files', $diagEmfile);

        $diagEnospc = LinuxSyscall::resolveDiagnosticMessage(28, 'write');
        $this->assertStringContainsString('No space left on device', $diagEnospc);

        $diagEnosys = LinuxSyscall::resolveDiagnosticMessage(38, 'syncfs');
        $this->assertStringContainsString('Function not implemented', $diagEnosys);

        $diagUnknown = LinuxSyscall::resolveDiagnosticMessage(999, 'custom_call');
        $this->assertStringContainsString('custom_call', $diagUnknown);
    }

    public function testLinuxSyscallCheckResultSuccessAndFailure(): void
    {
        $syscall = new LinuxSyscall();

        // Positive result does not throw
        $syscall->checkResult(0, 'mount');
        $syscall->checkResult(42, 'open');

        // Negative result throws SyscallException
        try {
            $syscall->checkResult(-1, 'pivot_root', ['target' => '/invalid/path']);
            $this->fail("Expected SyscallException was not thrown");
        } catch (SyscallException $e) {
            $this->assertEquals('pivot_root', $e->getSyscall());
            $this->assertEquals('/invalid/path', $e->getContext()['target']);
            $this->assertTrue($e->getErrno() >= 0);
        }
    }

    public function testLinuxSyscallFfiBindingWhenAvailable(): void
    {
        if (!LinuxSyscall::isAvailable()) {
            $this->assertFalse(LinuxSyscall::isAvailable());
            return;
        }

        $syscall = new LinuxSyscall();
        $pid = $syscall->getPid();
        $this->assertTrue($pid > 0);

        $euid = $syscall->getEuid();
        $this->assertTrue($euid >= 0);

        $errStr = $syscall->getErrorString(0);
        $this->assertNotEmpty($errStr);
    }
}
