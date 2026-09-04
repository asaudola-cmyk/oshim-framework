<?php
declare(strict_types=1);

namespace Oshim\Epp\Exceptions;

/**
 * Thrown when an EPP response indicates a non-success result code (2000-2502).
 */
class EppResponseException extends EppException
{
    private int $resultCode;
    private string $resultMessage;
    private ?string $clTRID;
    private ?string $svTRID;

    public function __construct(
        int $resultCode,
        string $resultMessage,
        ?string $clTRID = null,
        ?string $svTRID = null,
        ?\Throwable $previous = null
    ) {
        $this->resultCode = $resultCode;
        $this->resultMessage = $resultMessage;
        $this->clTRID = $clTRID;
        $this->svTRID = $svTRID;

        $msg = sprintf("EPP Command failed with result code %d: %s", $resultCode, $resultMessage);
        if ($clTRID !== null) {
            $msg .= " [clTRID: {$clTRID}]";
        }
        if ($svTRID !== null) {
            $msg .= " [svTRID: {$svTRID}]";
        }

        parent::__construct($msg, $resultCode, $previous);
    }

    public function getResultCode(): int
    {
        return $this->resultCode;
    }

    public function getResultMessage(): string
    {
        return $this->resultMessage;
    }

    public function getClTRID(): ?string
    {
        return $this->clTRID;
    }

    public function getSvTRID(): ?string
    {
        return $this->svTRID;
    }
}
