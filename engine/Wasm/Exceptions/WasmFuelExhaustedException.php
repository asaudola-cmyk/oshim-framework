<?php
declare(strict_types=1);

namespace Oshim\Wasm\Exceptions;

/**
 * Exception thrown when the execution fuel/instruction budget is depleted.
 */
class WasmFuelExhaustedException extends WasmTrapException
{
    private int $fuelLimit;
    private int $instructionsExecuted;

    public function __construct(int $fuelLimit = 0, int $instructionsExecuted = 0, ?\Throwable $previous = null)
    {
        $this->fuelLimit = $fuelLimit;
        $this->instructionsExecuted = $instructionsExecuted;

        $msg = sprintf(
            'Execution fuel exhausted: reached limit of %d instructions (executed %d instructions)',
            $fuelLimit,
            $instructionsExecuted
        );
        parent::__construct($msg, 'fuel_exhausted', 0, $previous);
    }

    public function getFuelLimit(): int
    {
        return $this->fuelLimit;
    }

    public function getInstructionsExecuted(): int
    {
        return $this->instructionsExecuted;
    }
}
