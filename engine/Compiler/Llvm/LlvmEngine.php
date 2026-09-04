<?php
declare(strict_types=1);

namespace Oshim\Compiler\Llvm;

use FFI;
use RuntimeException;

/**
 * 👑 Sovereign LLVM Native Compiler Bridge (AOT & JIT Compiler)
 * 
 * ADVANCED IMPLEMENTATION: Not just generating IR, but actually 
 * JIT-compiling the IR into native CPU Machine Code and executing it in-memory.
 */
class LlvmEngine
{
    protected ?FFI $ffi = null;
    protected bool $isSupported = false;
    protected string $errorMsg = '';

    public function __construct()
    {
        if (!extension_loaded('ffi')) {
            $this->errorMsg = "FFI extension is required for LLVM compilation.";
            return;
        }

        try {
            $libNames = ['libLLVM-c.so', 'libLLVM.so', 'libLLVM.dylib'];
            $loaded = false;
            
            foreach ($libNames as $libName) {
                try {
                    $this->ffi = FFI::cdef($this->getLlvmHeaders(), $libName);
                    $loaded = true;
                    break;
                } catch (\Throwable $e) {
                    continue;
                }
            }

            if (!$loaded) {
                throw new RuntimeException("Could not load LLVM C API Library (libLLVM.so).");
            }

            // Must initialize native target to execute Machine Code!
            $this->ffi->LLVMInitializeNativeTarget();
            $this->ffi->LLVMInitializeNativeAsmPrinter();
            $this->ffi->LLVMLinkInMCJIT();

            $this->isSupported = true;
        } catch (\Throwable $e) {
            $this->errorMsg = $e->getMessage();
        }
    }

    public function isSupported(): bool
    {
        return $this->isSupported;
    }

    public function getError(): string
    {
        return $this->errorMsg;
    }

    /**
     * ADVANCED: Compiles and EXECUTES a function in Native Machine Code instantly!
     * 
     * @return int The result directly from the CPU register
     */
    public function executeAddFunction(int $a, int $b): int
    {
        if (!$this->isSupported) {
            throw new RuntimeException("LLVM not supported: " . $this->errorMsg);
        }

        $module = $this->ffi->LLVMModuleCreateWithName("oshim_jit_module");
        $int32Type = $this->ffi->LLVMInt32Type();
        $paramTypes = $this->ffi->new("LLVMTypeRef[2]");
        $paramTypes[0] = $int32Type;
        $paramTypes[1] = $int32Type;
        
        $funcType = $this->ffi->LLVMFunctionType($int32Type, $paramTypes, 2, 0);
        $func = $this->ffi->LLVMAddFunction($module, "oshim_add_jit", $funcType);

        $block = $this->ffi->LLVMAppendBasicBlock($func, "entry");
        $builder = $this->ffi->LLVMCreateBuilder();
        $this->ffi->LLVMPositionBuilderAtEnd($builder, $block);

        $paramA = $this->ffi->LLVMGetParam($func, 0);
        $paramB = $this->ffi->LLVMGetParam($func, 1);
        $sum = $this->ffi->LLVMBuildAdd($builder, $paramA, $paramB, "sum");
        $this->ffi->LLVMBuildRet($builder, $sum);

        // --- THE MAGIC: MCJIT EXECUTION ---
        $engineOut = $this->ffi->new("LLVMExecutionEngineRef");
        $errorOut = $this->ffi->new("char*");
        
        if ($this->ffi->LLVMCreateExecutionEngineForModule(FFI::addr($engineOut), $module, FFI::addr($errorOut)) !== 0) {
            throw new RuntimeException("Failed to create LLVM Execution Engine");
        }
        $executionEngine = $engineOut;

        // Prepare Arguments
        $args = $this->ffi->new("LLVMGenericValueRef[2]");
        $args[0] = $this->ffi->LLVMCreateGenericValueOfInt($int32Type, $a, 0);
        $args[1] = $this->ffi->LLVMCreateGenericValueOfInt($int32Type, $b, 0);

        // Run Machine Code Natively!
        $resultVal = $this->ffi->LLVMRunFunction($executionEngine, $func, 2, $args);
        
        // Extract Integer Result
        $resultInt = $this->ffi->LLVMGenericValueToInt($resultVal, 0);

        // Cleanup
        $this->ffi->LLVMDisposeGenericValue($resultVal);
        $this->ffi->LLVMDisposeGenericValue($args[0]);
        $this->ffi->LLVMDisposeGenericValue($args[1]);
        $this->ffi->LLVMDisposeExecutionEngine($executionEngine);
        // Note: Disposing ExecutionEngine also disposes the attached module
        $this->ffi->LLVMDisposeBuilder($builder);

        return $resultInt;
    }

    protected function getLlvmHeaders(): string
    {
        return "
            typedef struct LLVMOpaqueModule *LLVMModuleRef;
            typedef struct LLVMOpaqueType *LLVMTypeRef;
            typedef struct LLVMOpaqueValue *LLVMValueRef;
            typedef struct LLVMOpaqueBasicBlock *LLVMBasicBlockRef;
            typedef struct LLVMOpaqueBuilder *LLVMBuilderRef;
            typedef struct LLVMOpaqueExecutionEngine *LLVMExecutionEngineRef;
            typedef struct LLVMOpaqueGenericValue *LLVMGenericValueRef;

            LLVMModuleRef LLVMModuleCreateWithName(const char *ModuleID);
            void LLVMDisposeModule(LLVMModuleRef M);
            
            LLVMTypeRef LLVMInt32Type(void);
            LLVMTypeRef LLVMFunctionType(LLVMTypeRef ReturnType, LLVMTypeRef *ParamTypes, unsigned ParamCount, int IsVarArg);
            
            LLVMValueRef LLVMAddFunction(LLVMModuleRef M, const char *Name, LLVMTypeRef FunctionTy);
            LLVMValueRef LLVMGetParam(LLVMValueRef Fn, unsigned Index);
            
            LLVMBasicBlockRef LLVMAppendBasicBlock(LLVMValueRef Fn, const char *Name);
            
            LLVMBuilderRef LLVMCreateBuilder(void);
            void LLVMPositionBuilderAtEnd(LLVMBuilderRef Builder, LLVMBasicBlockRef Block);
            void LLVMDisposeBuilder(LLVMBuilderRef Builder);
            
            LLVMValueRef LLVMBuildAdd(LLVMBuilderRef, LLVMValueRef LHS, LLVMValueRef RHS, const char *Name);
            LLVMValueRef LLVMBuildRet(LLVMBuilderRef, LLVMValueRef V);

            // JIT EXCECUTION ENGINE C-API
            void LLVMInitializeNativeTarget(void);
            void LLVMInitializeNativeAsmPrinter(void);
            void LLVMLinkInMCJIT(void);

            int LLVMCreateExecutionEngineForModule(LLVMExecutionEngineRef *OutEE, LLVMModuleRef M, char **OutError);
            void LLVMDisposeExecutionEngine(LLVMExecutionEngineRef EE);
            
            LLVMGenericValueRef LLVMCreateGenericValueOfInt(LLVMTypeRef Ty, unsigned long long N, int IsSigned);
            unsigned long long LLVMGenericValueToInt(LLVMGenericValueRef GenVal, int IsSigned);
            void LLVMDisposeGenericValue(LLVMGenericValueRef GenVal);
            
            LLVMGenericValueRef LLVMRunFunction(LLVMExecutionEngineRef EE, LLVMValueRef F, unsigned NumArgs, LLVMGenericValueRef *Args);
        ";
    }
}
