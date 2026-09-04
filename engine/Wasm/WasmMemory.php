<?php
declare(strict_types=1);

namespace Oshim\Wasm;

use Oshim\Wasm\Exceptions\WasmMemoryOutOfBoundsException;

/**
 * WebAssembly 1.0 Linear Memory Implementation.
 * Manages 64KiB memory pages, bounds checking, typed loads/stores with sign/zero extension.
 */
class WasmMemory
{
    public const PAGE_SIZE = 65536; // 64 KiB
    public const MAX_ALLOWED_PAGES = 65536; // 4 GiB max for 32-bit wasm memory

    private string $buffer;
    private int $pages;
    private ?int $maxPages;
    private ?int $sandboxMaxPages;

    public function __construct(int $initialPages = 1, ?int $maxPages = null, ?int $sandboxMaxPages = null)
    {
        $this->pages = max(0, $initialPages);
        $this->maxPages = $maxPages;
        $this->sandboxMaxPages = $sandboxMaxPages;

        $byteSize = $this->pages * self::PAGE_SIZE;
        $this->buffer = str_repeat("\x00", $byteSize);
    }

    /**
     * Get current memory size in pages (for `memory.size` opcode).
     */
    public function size(): int
    {
        return $this->pages;
    }

    /**
     * Get current memory size in bytes.
     */
    public function getByteSize(): int
    {
        return strlen($this->buffer);
    }

    /**
     * Grow memory by delta pages (for `memory.grow` opcode).
     * Returns previous page count on success, or -1 on failure.
     */
    public function grow(int $deltaPages): int
    {
        if ($deltaPages < 0) {
            return -1;
        }
        if ($deltaPages === 0) {
            return $this->pages;
        }

        $newPages = $this->pages + $deltaPages;

        // Check module limits
        if ($this->maxPages !== null && $newPages > $this->maxPages) {
            return -1;
        }

        // Check sandbox quota limits
        if ($this->sandboxMaxPages !== null && $newPages > $this->sandboxMaxPages) {
            return -1;
        }

        // Check maximum 32-bit wasm pages (4GB)
        if ($newPages > self::MAX_ALLOWED_PAGES) {
            return -1;
        }

        $oldPages = $this->pages;
        $additionalBytes = $deltaPages * self::PAGE_SIZE;
        $this->buffer .= str_repeat("\x00", $additionalBytes);
        $this->pages = $newPages;

        return $oldPages;
    }

    /**
     * Check if memory access range is within bounds.
     */
    public function checkBounds(int $offset, int $length): void
    {
        $totalBytes = strlen($this->buffer);
        if ($offset < 0 || $length < 0 || ($offset + $length) > $totalBytes) {
            throw new WasmMemoryOutOfBoundsException($offset, $length, $totalBytes);
        }
    }

    /**
     * Read raw bytes from linear memory.
     */
    public function readBytes(int $offset, int $length): string
    {
        $this->checkBounds($offset, $length);
        if ($length === 0) {
            return '';
        }
        return substr($this->buffer, $offset, $length);
    }

    /**
     * Write raw bytes to linear memory.
     */
    public function writeBytes(int $offset, string $bytes): void
    {
        $len = strlen($bytes);
        if ($len === 0) {
            return;
        }
        $this->checkBounds($offset, $len);

        for ($i = 0; $i < $len; $i++) {
            $this->buffer[$offset + $i] = $bytes[$i];
        }
    }

    // --- Typed Load Operations ---

    public function loadI32(int $offset): int
    {
        $this->checkBounds($offset, 4);
        $bytes = substr($this->buffer, $offset, 4);
        $val = unpack('V', $bytes)[1];
        // Convert unsigned 32-bit to signed 32-bit
        if ($val & 0x80000000) {
            $val = $val - 0x100000000;
        }
        return $val;
    }

    public function loadI64(int $offset): int
    {
        $this->checkBounds($offset, 8);
        $bytes = substr($this->buffer, $offset, 8);
        $val = unpack('P', $bytes)[1]; // 64-bit little endian unsigned
        return (int) $val;
    }

    public function loadF32(int $offset): float
    {
        $this->checkBounds($offset, 4);
        $bytes = substr($this->buffer, $offset, 4);
        return (float) unpack('g', $bytes)[1];
    }

    public function loadF64(int $offset): float
    {
        $this->checkBounds($offset, 8);
        $bytes = substr($this->buffer, $offset, 8);
        return (float) unpack('e', $bytes)[1];
    }

    public function loadI32_8s(int $offset): int
    {
        $this->checkBounds($offset, 1);
        $val = ord($this->buffer[$offset]);
        return ($val & 0x80) ? ($val - 0x100) : $val;
    }

    public function loadI32_8u(int $offset): int
    {
        $this->checkBounds($offset, 1);
        return ord($this->buffer[$offset]);
    }

    public function loadI32_16s(int $offset): int
    {
        $this->checkBounds($offset, 2);
        $val = unpack('v', substr($this->buffer, $offset, 2))[1];
        return ($val & 0x8000) ? ($val - 0x10000) : $val;
    }

    public function loadI32_16u(int $offset): int
    {
        $this->checkBounds($offset, 2);
        return (int) unpack('v', substr($this->buffer, $offset, 2))[1];
    }

    public function loadI64_8s(int $offset): int
    {
        return $this->loadI32_8s($offset);
    }

    public function loadI64_8u(int $offset): int
    {
        return $this->loadI32_8u($offset);
    }

    public function loadI64_16s(int $offset): int
    {
        return $this->loadI32_16s($offset);
    }

    public function loadI64_16u(int $offset): int
    {
        return $this->loadI32_16u($offset);
    }

    public function loadI64_32s(int $offset): int
    {
        return $this->loadI32($offset);
    }

    public function loadI64_32u(int $offset): int
    {
        $this->checkBounds($offset, 4);
        return (int) unpack('V', substr($this->buffer, $offset, 4))[1];
    }

    // --- Typed Store Operations ---

    public function storeI32(int $offset, int $value): void
    {
        $this->checkBounds($offset, 4);
        $packed = pack('V', $value & 0xFFFFFFFF);
        $this->writeBytes($offset, $packed);
    }

    public function storeI64(int $offset, int $value): void
    {
        $this->checkBounds($offset, 8);
        $packed = pack('P', $value);
        $this->writeBytes($offset, $packed);
    }

    public function storeF32(int $offset, float $value): void
    {
        $this->checkBounds($offset, 4);
        $packed = pack('g', $value);
        $this->writeBytes($offset, $packed);
    }

    public function storeF64(int $offset, float $value): void
    {
        $this->checkBounds($offset, 8);
        $packed = pack('e', $value);
        $this->writeBytes($offset, $packed);
    }

    public function storeI32_8(int $offset, int $value): void
    {
        $this->checkBounds($offset, 1);
        $this->buffer[$offset] = chr($value & 0xFF);
    }

    public function storeI32_16(int $offset, int $value): void
    {
        $this->checkBounds($offset, 2);
        $packed = pack('v', $value & 0xFFFF);
        $this->writeBytes($offset, $packed);
    }

    public function storeI64_8(int $offset, int $value): void
    {
        $this->storeI32_8($offset, $value);
    }

    public function storeI64_16(int $offset, int $value): void
    {
        $this->storeI32_16($offset, $value);
    }

    public function storeI64_32(int $offset, int $value): void
    {
        $this->storeI32($offset, $value);
    }

    // --- String & Interop Helpers ---

    /**
     * Read null-terminated C-string from memory.
     */
    public function readCString(int $offset, int $maxLen = 65536): string
    {
        $result = '';
        $curr = $offset;
        $totalBytes = strlen($this->buffer);

        while ($curr < $totalBytes && strlen($result) < $maxLen) {
            $char = $this->buffer[$curr++];
            if ($char === "\x00") {
                break;
            }
            $result .= $char;
        }

        return $result;
    }

    /**
     * Write string into memory, optionally appending null terminator.
     */
    public function writeString(int $offset, string $str, bool $nullTerminate = false): int
    {
        $data = $nullTerminate ? $str . "\x00" : $str;
        $this->writeBytes($offset, $data);
        return strlen($data);
    }

    public function storeU8(int $offset, int $value): void
    {
        $this->storeI32_8($offset, $value);
    }

    public function loadU8(int $offset): int
    {
        return $this->loadI32_8u($offset);
    }

    public function storeString(int $offset, string $str): int
    {
        return $this->writeString($offset, $str);
    }

    public function loadString(int $offset, int $length): string
    {
        return $this->readBytes($offset, $length);
    }

    public function byteLength(): int
    {
        return $this->getByteSize();
    }

    /**
     * Get underlying memory buffer.
     */
    public function getBuffer(): string
    {
        return $this->buffer;
    }
}
