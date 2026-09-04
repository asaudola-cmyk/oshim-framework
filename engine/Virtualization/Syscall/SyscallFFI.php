<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Syscall;

use FFI;
use RuntimeException;
use stdClass;

/**
 * 👑 Sovereign OS Interface (FFI Bridge)
 * 
 * WHY: Breaks PHP out of the web-server sandbox. Allows OSHIM to call OS-level 
 * C functions (libc) directly, exactly like Rust or Go. No C-extensions required.
 */
class SyscallFFI
{
    protected ?FFI $ffi = null;
    protected bool $supported = false;

    public function __construct()
    {
        // Edge Case: FFI must be enabled in php.ini (ffi.enable=true or ffi.enable=cli)
        if (!extension_loaded('ffi')) {
            error_log("OSHIM SyscallFFI: FFI extension is not loaded. OS-level operations disabled.");
            return;
        }

        try {
            // Determine OS to load the correct libc library
            $os = PHP_OS_FAMILY;
            $libc = match ($os) {
                'Linux' => 'libc.so.6',
                'Darwin' => 'libc.dylib',
                'Windows' => 'msvcrt.dll', // Basic C runtime for Windows
                default => 'libc.so'
            };

            // Define C signatures for systems programming
            // Edge Case: Handling struct definitions cross-platform is tricky, 
            // keeping it strictly POSIX standard for safety.
            $this->ffi = FFI::cdef("
                typedef long time_t;
                typedef int pid_t;
                
                // Get process ID
                pid_t getpid(void);
                
                // Native sleep (bypasses PHP execution limits theoretically)
                unsigned int sleep(unsigned int seconds);
                
                // Get system memory info (Linux specific struct sysinfo)
                struct sysinfo {
                    long uptime;
                    unsigned long loads[3];
                    unsigned long totalram;
                    unsigned long freeram;
                    unsigned long sharedram;
                    unsigned long bufferram;
                    unsigned long totalswap;
                    unsigned long freeswap;
                    unsigned short procs;
                    unsigned short pad;
                    unsigned long totalhigh;
                    unsigned long freehigh;
                    unsigned int mem_unit;
                    char _f[20-2*sizeof(long)-sizeof(int)];
                };
                int sysinfo(struct sysinfo *info);

            ", $libc);

            $this->supported = true;
        } catch (\Throwable $e) {
            error_log("OSHIM SyscallFFI Init Failed: " . $e->getMessage());
        }
    }

    public function isSupported(): bool
    {
        return $this->supported;
    }

    /**
     * Get Native OS Process ID (Bypassing PHP userland)
     */
    public function getNativePid(): int
    {
        if (!$this->supported) {
            return getmypid() ?: -1;
        }
        return $this->ffi->getpid();
    }

    /**
     * Fetch actual Hardware RAM stats directly from Linux Kernel via syscall.
     * WHY: PHP's memory_get_usage() only shows PHP's sandbox. We want the Whole OS memory.
     */
    public function getSystemMemoryInfo(): ?stdClass
    {
        if (!$this->supported || PHP_OS_FAMILY !== 'Linux') {
            return null; // Struct sysinfo is Linux only
        }

        try {
            $info = $this->ffi->new("struct sysinfo");
            if ($this->ffi->sysinfo(FFI::addr($info)) === 0) {
                $unit = (int) $info->mem_unit;
                if ($unit === 0) $unit = 1; // Failsafe
                
                $result = new stdClass();
                $result->uptime = (int)$info->uptime;
                $result->total_ram = (int)$info->totalram * $unit;
                $result->free_ram = (int)$info->freeram * $unit;
                $result->procs = (int)$info->procs;
                return $result;
            }
        } catch (\Throwable $e) {
            // Silently handle if struct mismatch
        }

        return null;
    }
}
