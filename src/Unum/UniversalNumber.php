<?php

declare(strict_types=1);

namespace Unum;

/**
 * 👑 Universal Number (UNUM 64-bit Mathematical Primitive)
 * 
 * WHY: In traditional computing, data types, opcodes, registers, and operations
 * are split across disconnected memory structures. A Universal Number packs
 * the entire operational invariant into a single 64-bit integer (GF(2^64)),
 * enabling direct single-cycle CPU register mapping.
 * 
 * Bitfield Specification:
 * - Bits 63..56 (8b): Opcode / ALU Function
 * - Bits 55..48 (8b): Physics State / Type (Posit32, Int64, Vector, Pointer)
 * - Bits 47..40 (8b): CPU Hardware Register Map (Dst 4b, Src 4b)
 * - Bits 39..32 (8b): SIMD / Vector Mode (Scalar, AVX-128, AVX2-256, AVX-512)
 * - Bits 31..0  (32b): Projective Riemann Fraction / Immediate Payload
 */
final class UniversalNumber
{
    public const SHIFT_OPCODE  = 56;
    public const SHIFT_TYPE    = 48;
    public const SHIFT_REG     = 40;
    public const SHIFT_SIMD    = 32;
    public const SHIFT_PAYLOAD = 0;

    public const MASK_BYTE    = 0xFF;
    public const MASK_PAYLOAD = 0xFFFFFFFF;

    /* Opcodes */
    public const OP_NOP      = 0x00;
    public const OP_MOV_IMM  = 0x01;
    public const OP_MOV_REG  = 0x02;
    public const OP_ADD_IMM  = 0x03;
    public const OP_ADD_REG  = 0x04;
    public const OP_SUB_IMM  = 0x05;
    public const OP_SUB_REG  = 0x06;
    public const OP_MUL_REG  = 0x07;
    public const OP_XOR_REG  = 0x08;
    public const OP_LOOP_DEC   = 0x09;
    public const OP_LOOP_START = 0x0E;
    public const OP_SIMD_DOT   = 0x10;
    public const OP_SIMD_ADD = 0x11;
    public const OP_RET      = 0xFE;
    public const OP_HALT     = 0xFF;

    /* Hardware Register Identifiers (System V AMD64) */
    public const REG_RAX = 0;
    public const REG_RCX = 1;
    public const REG_RDX = 2;
    public const REG_RBX = 3;
    public const REG_RSP = 4;
    public const REG_RBP = 5;
    public const REG_RSI = 6;
    public const REG_RDI = 7;
    public const REG_R8  = 8;
    public const REG_R9  = 9;
    public const REG_R10 = 10;
    public const REG_R11 = 11;
    public const REG_R12 = 12;
    public const REG_R13 = 13;
    public const REG_R14 = 14;
    public const REG_R15 = 15;

    /* Types */
    public const TYPE_RAW_INT64   = 0x01;
    public const TYPE_IEEE_FLOAT  = 0x02;
    public const TYPE_POSIT32     = 0x03;
    public const TYPE_VECTOR128   = 0x04;
    public const TYPE_VECTOR256   = 0x05;
    public const TYPE_VECTOR512   = 0x06;
    public const TYPE_RAW_POINTER = 0x07;

    private int $value;

    private function __construct(int $value)
    {
        $this->value = $value;
    }

    /**
     * Constructs a 64-bit universal number from individual fields.
     */
    public static function pack(
        int $opcode,
        int $type = self::TYPE_RAW_INT64,
        int $regDest = 0,
        int $regSrc = 0,
        int $simd = 0,
        int $payload = 0
    ): self {
        $regPacked = (($regDest & 0x0F) << 4) | ($regSrc & 0x0F);

        $val = (($opcode & self::MASK_BYTE) << self::SHIFT_OPCODE) |
               (($type & self::MASK_BYTE) << self::SHIFT_TYPE) |
               (($regPacked & self::MASK_BYTE) << self::SHIFT_REG) |
               (($simd & self::MASK_BYTE) << self::SHIFT_SIMD) |
               ($payload & self::MASK_PAYLOAD);

        return new self($val);
    }

    /**
     * Wrap existing 64-bit integer.
     */
    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function getOpcode(): int
    {
        return ($this->value >> self::SHIFT_OPCODE) & self::MASK_BYTE;
    }

    public function getType(): int
    {
        return ($this->value >> self::SHIFT_TYPE) & self::MASK_BYTE;
    }

    public function getRegDest(): int
    {
        $regPacked = ($this->value >> self::SHIFT_REG) & self::MASK_BYTE;
        return ($regPacked >> 4) & 0x0F;
    }

    public function getRegSrc(): int
    {
        $regPacked = ($this->value >> self::SHIFT_REG) & self::MASK_BYTE;
        return $regPacked & 0x0F;
    }

    public function getSimd(): int
    {
        return ($this->value >> self::SHIFT_SIMD) & self::MASK_BYTE;
    }

    public function getPayload(): int
    {
        return $this->value & self::MASK_PAYLOAD;
    }

    public function toHex(): string
    {
        return sprintf('0x%016X', $this->value);
    }

    public function toBinary(): string
    {
        return sprintf('%064b', $this->value);
    }

    public function formatInspection(): string
    {
        return sprintf(
            '[UNUM %s] OP: 0x%02X | TYPE: 0x%02X | DST: R%d | SRC: R%d | SIMD: %d | PAYLOAD: %d',
            $this->toHex(),
            $this->getOpcode(),
            $this->getType(),
            $this->getRegDest(),
            $this->getRegSrc(),
            $this->getSimd(),
            $this->getPayload()
        );
    }
}
