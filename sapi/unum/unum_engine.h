/**
 * 👑 UNUM Universal Number Bare-Metal Compiler Engine
 * 
 * WHY: Traditional compilers treat computation as text strings and complex AST graphs.
 * UNUM treats computation as physics and mathematics: every operation, type, CPU register,
 * and immediate operand is compressed into a single 64-bit Universal Number (U in GF(2^64)).
 * 
 * Target Architecture: Linux x86_64 (AMD64 / Intel 64) with AVX2 & AVX-512 extensions.
 */

#ifndef UNUM_ENGINE_H
#define UNUM_ENGINE_H

#include <stdint.h>
#include <stddef.h>
#include <stdbool.h>

#ifdef __cplusplus
extern "C" {
#endif

/* ------------------------------------------------------------------------ */
/* 64-Bit Universal Number Type & Bitfield Layout                           */
/* ------------------------------------------------------------------------ */
/*
 * Layout:
 * Bits 63..56 (8 bits): Opcode / ALU Function
 * Bits 55..48 (8 bits): Physics State / Type (Posit32, Int64, Vector, Pointer)
 * Bits 47..40 (8 bits): CPU Hardware Register Map (Bits 47..44: Dst, Bits 43..40: Src)
 * Bits 39..32 (8 bits): SIMD / Vector Lane State (Scalar, 128-bit, 256-bit, 512-bit)
 * Bits 31..0  (32 bits): Projective Riemann Payload / Immediate / Branch Offset
 */

typedef uint64_t unum_t;

#define UNUM_SHIFT_OPCODE   56
#define UNUM_SHIFT_TYPE     48
#define UNUM_SHIFT_REG      40
#define UNUM_SHIFT_SIMD     32
#define UNUM_SHIFT_PAYLOAD   0

#define UNUM_MASK_BYTE      0xFFULL
#define UNUM_MASK_PAYLOAD   0xFFFFFFFFULL

/* ------------------------------------------------------------------------ */
/* Universal Opcodes (Operation Invariants)                                 */
/* ------------------------------------------------------------------------ */
typedef enum {
    UNUM_OP_NOP        = 0x00, /* No operation */
    UNUM_OP_MOV_IMM    = 0x01, /* mov reg_dest, imm32 */
    UNUM_OP_MOV_REG    = 0x02, /* mov reg_dest, reg_src */
    UNUM_OP_ADD_IMM    = 0x03, /* add reg_dest, imm32 */
    UNUM_OP_ADD_REG    = 0x04, /* add reg_dest, reg_src */
    UNUM_OP_SUB_IMM    = 0x05, /* sub reg_dest, imm32 */
    UNUM_OP_SUB_REG    = 0x06, /* sub reg_dest, reg_src */
    UNUM_OP_MUL_REG    = 0x07, /* imul reg_dest, reg_src */
    UNUM_OP_XOR_REG    = 0x08, /* xor reg_dest, reg_src */
    UNUM_OP_LOOP_DEC   = 0x09, /* dec reg_counter; jnz loop_start */
    UNUM_OP_LOOP_START = 0x0E, /* Demarcate start of hardware loop body */
    UNUM_OP_SIMD_DOT   = 0x10, /* Hardware AVX2/AVX-512 Vector Dot Product */
    UNUM_OP_SIMD_ADD   = 0x11, /* Hardware AVX2 Vector Addition */
    UNUM_OP_RET        = 0xFE, /* Return RAX to caller */
    UNUM_OP_HALT       = 0xFF  /* Terminate execution */
} unum_opcode_t;

/* ------------------------------------------------------------------------ */
/* Mathematical Physics State Types                                         */
/* ------------------------------------------------------------------------ */
typedef enum {
    UNUM_TYPE_RAW_INT64   = 0x01, /* 64-bit Two's Complement Integer */
    UNUM_TYPE_IEEE_FLOAT  = 0x02, /* Standard 64-bit IEEE-754 Float */
    UNUM_TYPE_POSIT32     = 0x03, /* Type-III Unum / Posit logarithmic encoding */
    UNUM_TYPE_VECTOR128   = 0x04, /* 128-bit 4xFloat32 SIMD lane */
    UNUM_TYPE_VECTOR256   = 0x05, /* 256-bit 8xFloat32 AVX2 lane */
    UNUM_TYPE_VECTOR512   = 0x06, /* 512-bit 16xFloat32 AVX-512 lane */
    UNUM_TYPE_RAW_POINTER = 0x07  /* Direct virtual memory address */
} unum_type_t;

/* ------------------------------------------------------------------------ */
/* CPU Hardware Register Mapping (System V AMD64 ABI)                      */
/* ------------------------------------------------------------------------ */
typedef enum {
    UNUM_REG_RAX = 0,  /* Return value accumulator */
    UNUM_REG_RCX = 1,  /* Loop counter / 4th argument */
    UNUM_REG_RDX = 2,  /* 3rd argument / data register */
    UNUM_REG_RBX = 3,  /* Callee-saved base */
    UNUM_REG_RSP = 4,  /* Stack pointer */
    UNUM_REG_RBP = 5,  /* Frame base pointer */
    UNUM_REG_RSI = 6,  /* 2nd argument / source index */
    UNUM_REG_RDI = 7,  /* 1st argument / destination index */
    UNUM_REG_R8  = 8,  /* 5th argument */
    UNUM_REG_R9  = 9,  /* 6th argument */
    UNUM_REG_R10 = 10, /* Temporary scratchpad */
    UNUM_REG_R11 = 11, /* Temporary scratchpad */
    UNUM_REG_R12 = 12, /* Callee-saved */
    UNUM_REG_R13 = 13, /* Callee-saved */
    UNUM_REG_R14 = 14, /* Callee-saved */
    UNUM_REG_R15 = 15  /* Callee-saved */
} unum_reg_t;

/* ------------------------------------------------------------------------ */
/* Core Engine C API Prototypes                                             */
/* ------------------------------------------------------------------------ */

/* Encodes 5 discrete components into a single 64-bit universal number */
unum_t unum_encode(uint8_t op, uint8_t type, uint8_t reg_dest, uint8_t reg_src, uint8_t simd, uint32_t payload);

/* Decodes a 64-bit universal number into its constituent components */
void unum_decode(unum_t num, uint8_t *op, uint8_t *type, uint8_t *reg_dest, uint8_t *reg_src, uint8_t *simd, uint32_t *payload);

/* Allocates page-aligned executable memory (mmap PROT_READ|PROT_WRITE|PROT_EXEC) */
void* unum_alloc_executable_page(size_t size);

/* Frees an allocated executable memory page (munmap) */
int unum_free_executable_page(void *addr, size_t size);

/* Translates an array of universal numbers directly into x86_64 machine code */
int unum_emit_machine_code(const unum_t *numbers, size_t count, uint8_t *code_buffer, size_t max_size, size_t *emitted_size);

/* Executes native machine code page directly via hardware registers */
int64_t unum_execute(const void *code_page, int64_t arg1, int64_t arg2, int64_t arg3);

/* Hardware SIMD AVX-512 / AVX2 Vector Dot Product */
float unum_simd_dot_f32(const float *a, const float *b, size_t dim);

/* Hardware SIMD Batch Dot Product (runs 'count' vector dot products in pure C) */
float unum_simd_dot_batch(const float *a, const float *b, size_t dim, size_t count);

/* Hardware Tiled Matrix Multiplication (C = A x B, dimensions: M x K x N) */
void unum_tensor_matmul_f32(const float *A, const float *B, float *C, size_t M, size_t K, size_t N);

/* Neural Network Activations: 0=ReLU, 1=GELU, 2=Softmax */
void unum_tensor_activate_f32(float *data, size_t size, int activation_type);

/* Hardware Cosine Similarity with vectorized L2 normalization */
float unum_tensor_cosine_similarity(const float *a, const float *b, size_t dim);

/* CPU feature bitmask detection (AVX, AVX2, AVX-512, FMA) */
uint32_t unum_cpu_features(void);

#ifdef __cplusplus
}
#endif

#endif /* UNUM_ENGINE_H */
