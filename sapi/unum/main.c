/**
 * 👑 UNUM Universal Number Sovereign Launcher
 * 
 * WHY: Provides standalone CLI diagnostics, CPU instruction feature reporting,
 * and bare-metal execution verification without requiring external runtimes.
 */

#include <stdio.h>
#include <stdlib.h>
#include "unum_engine.h"

int main(int argc, char **argv)
{
    printf("\n========================================================================\n");
    printf("  👑 UNUM UNIVERSAL NUMBER BARE-METAL COMPILER (SOVEREIGN ENGINE)\n");
    printf("  ⚡ Direct Silicon Execution & x86_64 Machine Code Generator\n");
    printf("========================================================================\n");

    uint32_t features = unum_cpu_features();
    printf("  [Hardware CPU Detection]\n");
    printf("    ✔ AVX Supported     : %s\n", (features & 1) ? "YES" : "NO");
    printf("    ✔ AVX2 Supported    : %s\n", (features & 2) ? "YES" : "NO");
    printf("    ✔ AVX-512 Supported : %s\n", (features & 4) ? "YES" : "NO");
    printf("    ✔ FMA Supported     : %s\n", (features & 8) ? "YES" : "NO");

    /* Quick Bare-Metal Machine Code Verification */
    printf("\n  [Self-Test: Machine Code Direct Execution]\n");

    /* Program: Multiply argument 1 by 50, then return */
    unum_t program[4];
    /* mov rax, rdi (arg1) */
    program[0] = unum_encode(UNUM_OP_MOV_REG, UNUM_TYPE_RAW_INT64, UNUM_REG_RAX, UNUM_REG_RDI, 0, 0);
    /* mov rdx, 50 */
    program[1] = unum_encode(UNUM_OP_MOV_IMM, UNUM_TYPE_RAW_INT64, UNUM_REG_RDX, 0, 0, 50);
    /* imul rax, rdx */
    program[2] = unum_encode(UNUM_OP_MUL_REG, UNUM_TYPE_RAW_INT64, UNUM_REG_RAX, UNUM_REG_RDX, 0, 0);
    /* ret */
    program[3] = unum_encode(UNUM_OP_RET, UNUM_TYPE_RAW_INT64, UNUM_REG_RAX, 0, 0, 0);

    void *page = unum_alloc_executable_page(4096);
    if (!page) {
        fprintf(stderr, "    ❌ Failed to allocate executable memory page.\n");
        return 1;
    }

    size_t emitted = 0;
    int err = unum_emit_machine_code(program, 4, (uint8_t*)page, 4096, &emitted);
    if (err != 0) {
        fprintf(stderr, "    ❌ Failed to emit machine code: err=%d\n", err);
        unum_free_executable_page(page, 4096);
        return 1;
    }

    printf("    ✔ Compiled %zu machine bytes into executable memory (PROT_EXEC)\n", emitted);

    int64_t input = 42;
    int64_t result = unum_execute(page, input, 0, 0);
    printf("    ✔ Executed via CPU registers: %ld * 50 = %ld\n", input, result);

    unum_free_executable_page(page, 4096);

    if (result == 2100) {
        printf("    🚀 Bare-metal CPU execution verified 100%% accurate!\n");
    } else {
        printf("    ❌ Unexpected result: %ld\n", result);
        return 1;
    }

    printf("========================================================================\n\n");
    return 0;
}
