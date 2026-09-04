<?php
declare(strict_types=1);

namespace Oshim\Wasm;

use Oshim\Wasm\Exceptions\WasmParserException;

/**
 * Binary decoder for WebAssembly 1.0 (MVP) modules.
 * Decodes header, LEB128 integers, IEEE754 floats, and all standard sections (1-11 and 0 Custom).
 */
class WasmBinaryParser
{
    public const WASM_MAGIC = "\x00asm";
    public const WASM_VERSION = 1;

    // Section IDs
    public const SECTION_CUSTOM = 0;
    public const SECTION_TYPE = 1;
    public const SECTION_IMPORT = 2;
    public const SECTION_FUNCTION = 3;
    public const SECTION_TABLE = 4;
    public const SECTION_MEMORY = 5;
    public const SECTION_GLOBAL = 6;
    public const SECTION_EXPORT = 7;
    public const SECTION_START = 8;
    public const SECTION_ELEMENT = 9;
    public const SECTION_CODE = 10;
    public const SECTION_DATA = 11;

    private string $buffer;
    private int $length;
    private int $offset = 0;

    public function __construct(string $binary = '')
    {
        $this->buffer = $binary;
        $this->length = strlen($binary);
        $this->offset = 0;
    }

    /**
     * Parse binary string into a WasmModule instance.
     */
    public function parse(string $binary): WasmModule
    {
        $this->buffer = $binary;
        $this->length = strlen($binary);
        $this->offset = 0;

        return $this->parseModule();
    }

    /**
     * Parse binary file into a WasmModule instance.
     */
    public function parseFile(string $filePath): WasmModule
    {
        if (!file_exists($filePath)) {
            throw new WasmParserException("Wasm file not found: {$filePath}");
        }
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new WasmParserException("Failed to read Wasm file: {$filePath}");
        }
        return $this->parse($contents);
    }

    /**
     * Execute the full module parsing routine.
     */
    private function parseModule(): WasmModule
    {
        $module = new WasmModule();

        // 1. Verify Magic Number (4 bytes)
        if ($this->length < 8) {
            throw new WasmParserException('Binary too small to be a valid WebAssembly module', $this->offset);
        }

        $magic = $this->readBytes(4);
        if ($magic !== self::WASM_MAGIC) {
            throw new WasmParserException(
                sprintf('Invalid Wasm magic number: expected 0x0061736D, got %s', bin2hex($magic)),
                0
            );
        }

        // 2. Verify Version (4 bytes, little-endian uint32)
        $versionBytes = $this->readBytes(4);
        $version = unpack('V', $versionBytes)[1] ?? 0;
        if ($version !== self::WASM_VERSION) {
            throw new WasmParserException(
                sprintf('Unsupported WebAssembly version: %d (expected %d)', $version, self::WASM_VERSION),
                4
            );
        }

        // 3. Parse Sections
        $lastSectionId = -1;
        while ($this->offset < $this->length) {
            $sectionId = $this->readByte();
            $sectionSize = $this->readVarUint32();
            $sectionStart = $this->offset;
            $sectionEnd = $sectionStart + $sectionSize;

            if ($sectionEnd > $this->length) {
                throw new WasmParserException(
                    sprintf('Section %d size %d exceeds total file length %d', $sectionId, $sectionSize, $this->length),
                    $this->offset
                );
            }

            // Custom sections can appear anywhere. Known sections must appear in increasing order.
            if ($sectionId !== self::SECTION_CUSTOM) {
                if ($sectionId <= $lastSectionId) {
                    throw new WasmParserException(
                        sprintf('Section ID %d appeared out of order (previous was %d)', $sectionId, $lastSectionId),
                        $this->offset - 1
                    );
                }
                $lastSectionId = $sectionId;
            }

            switch ($sectionId) {
                case self::SECTION_CUSTOM:
                    $this->parseCustomSection($module, $sectionEnd);
                    break;
                case self::SECTION_TYPE:
                    $this->parseTypeSection($module);
                    break;
                case self::SECTION_IMPORT:
                    $this->parseImportSection($module);
                    break;
                case self::SECTION_FUNCTION:
                    $this->parseFunctionSection($module);
                    break;
                case self::SECTION_TABLE:
                    $this->parseTableSection($module);
                    break;
                case self::SECTION_MEMORY:
                    $this->parseMemorySection($module);
                    break;
                case self::SECTION_GLOBAL:
                    $this->parseGlobalSection($module);
                    break;
                case self::SECTION_EXPORT:
                    $this->parseExportSection($module);
                    break;
                case self::SECTION_START:
                    $this->parseStartSection($module);
                    break;
                case self::SECTION_ELEMENT:
                    $this->parseElementSection($module);
                    break;
                case self::SECTION_CODE:
                    $this->parseCodeSection($module);
                    break;
                case self::SECTION_DATA:
                    $this->parseDataSection($module);
                    break;
                default:
                    throw new WasmParserException(
                        sprintf('Unknown section ID: 0x%02X', $sectionId),
                        $this->offset - 1
                    );
            }

            // Ensure we are positioned at the end of the section
            if ($this->offset !== $sectionEnd) {
                $this->offset = $sectionEnd;
            }
        }

        // Validate Function and Code section parity
        if (count($module->functions) !== count($module->codes)) {
            throw new WasmParserException(
                sprintf(
                    'Function count (%d) does not match Code segment count (%d)',
                    count($module->functions),
                    count($module->codes)
                )
            );
        }

        return $module;
    }

    /**
     * Parse Section 0: Custom
     */
    private function parseCustomSection(WasmModule $module, int $sectionEnd): void
    {
        $name = $this->readName();
        $dataLen = $sectionEnd - $this->offset;
        $data = $dataLen > 0 ? $this->readBytes($dataLen) : '';
        $module->customSections[] = [
            'name' => $name,
            'data' => $data,
        ];
    }

    /**
     * Parse Section 1: Type (Function Signatures)
     */
    private function parseTypeSection(WasmModule $module): void
    {
        $count = $this->readVarUint32();
        for ($i = 0; $i < $count; $i++) {
            $form = $this->readByte();
            if ($form !== 0x60) {
                throw new WasmParserException(
                    sprintf('Invalid function type form: 0x%02X (expected 0x60)', $form),
                    $this->offset - 1
                );
            }

            $paramCount = $this->readVarUint32();
            $params = [];
            for ($p = 0; $p < $paramCount; $p++) {
                $params[] = $this->readByte();
            }

            $resultCount = $this->readVarUint32();
            $results = [];
            for ($r = 0; $r < $resultCount; $r++) {
                $results[] = $this->readByte();
            }

            $module->types[] = [
                'params'  => $params,
                'results' => $results,
            ];
        }
    }

    /**
     * Parse Section 2: Import
     */
    private function parseImportSection(WasmModule $module): void
    {
        $count = $this->readVarUint32();
        for ($i = 0; $i < $count; $i++) {
            $modName = $this->readName();
            $fieldName = $this->readName();
            $kind = $this->readByte();

            $desc = match ($kind) {
                WasmModule::KIND_FUNC => $this->readVarUint32(), // typeidx
                WasmModule::KIND_TABLE => [
                    'elemType' => $this->readByte(),
                    'limits'   => $this->readLimits(),
                ],
                WasmModule::KIND_MEMORY => $this->readLimits(),
                WasmModule::KIND_GLOBAL => [
                    'type'    => $this->readByte(),
                    'mutable' => $this->readByte() === 1,
                ],
                default => throw new WasmParserException("Unknown import kind: 0x{$kind}", $this->offset - 1),
            };

            $module->imports[] = [
                'module' => $modName,
                'name'   => $fieldName,
                'kind'   => $kind,
                'desc'   => $desc,
            ];
        }
    }

    /**
     * Parse Section 3: Function
     */
    private function parseFunctionSection(WasmModule $module): void
    {
        $count = $this->readVarUint32();
        for ($i = 0; $i < $count; $i++) {
            $module->functions[] = $this->readVarUint32();
        }
    }

    /**
     * Parse Section 4: Table
     */
    private function parseTableSection(WasmModule $module): void
    {
        $count = $this->readVarUint32();
        for ($i = 0; $i < $count; $i++) {
            $elemType = $this->readByte();
            $limits = $this->readLimits();
            $module->tables[] = [
                'elemType' => $elemType,
                'min'      => $limits['min'],
                'max'      => $limits['max'],
            ];
        }
    }

    /**
     * Parse Section 5: Memory
     */
    private function parseMemorySection(WasmModule $module): void
    {
        $count = $this->readVarUint32();
        for ($i = 0; $i < $count; $i++) {
            $limits = $this->readLimits();
            $module->memories[] = $limits;
        }
    }

    /**
     * Parse Section 6: Global
     */
    private function parseGlobalSection(WasmModule $module): void
    {
        $count = $this->readVarUint32();
        for ($i = 0; $i < $count; $i++) {
            $valType = $this->readByte();
            $mut = $this->readByte() === 1;
            $init = $this->readInitExpr();

            $module->globals[] = [
                'type'    => $valType,
                'mutable' => $mut,
                'init'    => $init,
            ];
        }
    }

    /**
     * Parse Section 7: Export
     */
    private function parseExportSection(WasmModule $module): void
    {
        $count = $this->readVarUint32();
        for ($i = 0; $i < $count; $i++) {
            $name = $this->readName();
            $kind = $this->readByte();
            $index = $this->readVarUint32();

            $module->exports[] = [
                'name'  => $name,
                'kind'  => $kind,
                'index' => $index,
            ];
        }
    }

    /**
     * Parse Section 8: Start
     */
    private function parseStartSection(WasmModule $module): void
    {
        $module->start = $this->readVarUint32();
    }

    /**
     * Parse Section 9: Element
     */
    private function parseElementSection(WasmModule $module): void
    {
        $count = $this->readVarUint32();
        for ($i = 0; $i < $count; $i++) {
            $tableIdx = $this->readVarUint32();
            $offsetExpr = $this->readInitExpr();
            $numFuncs = $this->readVarUint32();
            $init = [];
            for ($f = 0; $f < $numFuncs; $f++) {
                $init[] = $this->readVarUint32();
            }

            $module->elements[] = [
                'tableIndex' => $tableIdx,
                'offsetExpr' => $offsetExpr,
                'init'       => $init,
            ];
        }
    }

    /**
     * Parse Section 10: Code
     */
    private function parseCodeSection(WasmModule $module): void
    {
        $count = $this->readVarUint32();
        for ($i = 0; $i < $count; $i++) {
            $bodySize = $this->readVarUint32();
            $startOffset = $this->offset;

            $localVecCount = $this->readVarUint32();
            $locals = [];
            for ($v = 0; $v < $localVecCount; $v++) {
                $localCount = $this->readVarUint32();
                $localType = $this->readByte();
                for ($lc = 0; $lc < $localCount; $lc++) {
                    $locals[] = $localType;
                }
            }

            $codeBytesRemaining = $bodySize - ($this->offset - $startOffset);
            if ($codeBytesRemaining < 0) {
                throw new WasmParserException('Local variables declaration exceeded code body size', $this->offset);
            }

            $code = $codeBytesRemaining > 0 ? $this->readBytes($codeBytesRemaining) : '';

            $module->codes[] = [
                'locals' => $locals,
                'code'   => $code,
            ];
        }
    }

    /**
     * Parse Section 11: Data
     */
    private function parseDataSection(WasmModule $module): void
    {
        $count = $this->readVarUint32();
        for ($i = 0; $i < $count; $i++) {
            $memIdx = $this->readVarUint32();
            $offsetExpr = $this->readInitExpr();
            $dataSize = $this->readVarUint32();
            $data = $dataSize > 0 ? $this->readBytes($dataSize) : '';

            $module->data[] = [
                'memoryIndex' => $memIdx,
                'offsetExpr'  => $offsetExpr,
                'data'        => $data,
            ];
        }
    }

    /**
     * Read Limits structure: flag (0x00 min, 0x01 min & max).
     *
     * @return array{min: int, max: ?int}
     */
    public function readLimits(): array
    {
        $flag = $this->readByte();
        $min = $this->readVarUint32();
        $max = null;

        if ($flag === 0x01) {
            $max = $this->readVarUint32();
        } elseif ($flag !== 0x00) {
            throw new WasmParserException("Invalid limit flag: 0x{$flag}", $this->offset - 1);
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * Read init expression bytes until the 0x0B (end) opcode.
     */
    public function readInitExpr(): string
    {
        $start = $this->offset;
        $depth = 0;

        while ($this->offset < $this->length) {
            $byte = ord($this->buffer[$this->offset++]);
            if ($byte === 0x02 || $byte === 0x03 || $byte === 0x04) {
                // block, loop, if
                $depth++;
            } elseif ($byte === 0x0B) {
                if ($depth === 0) {
                    $exprLen = $this->offset - $start;
                    return substr($this->buffer, $start, $exprLen);
                }
                $depth--;
            }
        }

        throw new WasmParserException('Unterminated init expression (missing 0x0B end byte)', $this->offset);
    }

    /**
     * Read single byte.
     */
    public function readByte(): int
    {
        if ($this->offset >= $this->length) {
            throw new WasmParserException('Unexpected end of binary stream', $this->offset);
        }
        return ord($this->buffer[$this->offset++]);
    }

    /**
     * Read raw bytes.
     */
    public function readBytes(int $len): string
    {
        if ($len < 0 || ($this->offset + $len) > $this->length) {
            throw new WasmParserException(
                sprintf('Unexpected EOF: requested %d bytes, only %d available', $len, $this->length - $this->offset),
                $this->offset
            );
        }
        $bytes = substr($this->buffer, $this->offset, $len);
        $this->offset += $len;
        return $bytes;
    }

    /**
     * Read UTF-8 name (length prefixed LEB128 string).
     */
    public function readName(): string
    {
        $len = $this->readVarUint32();
        if ($len === 0) {
            return '';
        }
        return $this->readBytes($len);
    }

    /**
     * Read unsigned LEB128 integer (parameterizable bitwidth).
     */
    public function readVarUint(int $maxBits = 32): int
    {
        $result = 0;
        $shift = 0;
        $count = 0;
        $maxBytes = (int) ceil($maxBits / 7) + 1;

        while (true) {
            if ($this->offset >= $this->length) {
                throw new WasmParserException("Unexpected EOF reading LEB128 unsigned integer at offset {$this->offset}", $this->offset);
            }
            $byte = ord($this->buffer[$this->offset++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
            $count++;

            if (($byte & 0x80) === 0) {
                break;
            }
            if ($count > $maxBytes) {
                throw new WasmParserException("LEB128 unsigned integer exceeds {$maxBits} bits", $this->offset);
            }
        }

        return $result;
    }

    /**
     * Read signed LEB128 integer (parameterizable bitwidth).
     */
    public function readVarInt(int $maxBits = 32): int
    {
        $result = 0;
        $shift = 0;
        $count = 0;
        $byte = 0;
        $maxBytes = (int) ceil($maxBits / 7) + 1;

        while (true) {
            if ($this->offset >= $this->length) {
                throw new WasmParserException("Unexpected EOF reading LEB128 signed integer at offset {$this->offset}", $this->offset);
            }
            $byte = ord($this->buffer[$this->offset++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
            $count++;

            if (($byte & 0x80) === 0) {
                break;
            }
            if ($count > $maxBytes) {
                throw new WasmParserException("LEB128 signed integer exceeds {$maxBits} bits", $this->offset);
            }
        }

        if ($shift < $maxBits && ($byte & 0x40) !== 0) {
            $result |= (-1 << $shift);
        }

        if ($maxBits === 32) {
            if ($result & 0x80000000) {
                $result = ($result & 0xFFFFFFFF) - 0x100000000;
            }
        }

        return $result;
    }

    /**
     * Read unsigned LEB128 integer (up to 32 bits).
     */
    public function readVarUint32(): int
    {
        return $this->readVarUint(32);
    }

    /**
     * Read signed LEB128 integer (32-bit).
     */
    public function readVarInt32(): int
    {
        return $this->readVarInt(32);
    }

    /**
     * Read signed LEB128 integer (64-bit).
     */
    public function readVarInt64(): int
    {
        return $this->readVarInt(64);
    }

    /**
     * Read 32-bit floating point number (IEEE 754, little-endian).
     */
    public function readFloat32(): float
    {
        $bytes = $this->readBytes(4);
        $unpacked = unpack('g', $bytes);
        return (float) ($unpacked[1] ?? 0.0);
    }

    /**
     * Read 64-bit floating point number (IEEE 754 double, little-endian).
     */
    public function readFloat64(): float
    {
        $bytes = $this->readBytes(8);
        $unpacked = unpack('e', $bytes);
        return (float) ($unpacked[1] ?? 0.0);
    }

    /**
     * Get current read offset.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Get buffer length.
     */
    public function getLength(): int
    {
        return $this->length;
    }
}
