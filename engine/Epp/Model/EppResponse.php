<?php
declare(strict_types=1);

namespace Oshim\Epp\Model;

/**
 * Normalized EPP Response Object (RFC 5730 §3).
 */
class EppResponse
{
    private int $code;
    private string $message;
    private ?string $clTRID;
    private ?string $svTRID;
    private ?string $resDataXml;
    private ?array $data;
    private string $rawXml;

    public function __construct(
        int $code,
        string $message,
        ?string $clTRID = null,
        ?string $svTRID = null,
        ?string $resDataXml = null,
        ?array $data = null,
        string $rawXml = ''
    ) {
        $this->code = $code;
        $this->message = $message;
        $this->clTRID = $clTRID;
        $this->svTRID = $svTRID;
        $this->resDataXml = $resDataXml;
        $this->data = $data;
        $this->rawXml = $rawXml;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getClTRID(): ?string
    {
        return $this->clTRID;
    }

    public function getSvTRID(): ?string
    {
        return $this->svTRID;
    }

    public function getResDataXml(): ?string
    {
        return $this->resDataXml;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function getRawXml(): string
    {
        return $this->rawXml;
    }

    public function isSuccess(): bool
    {
        return $this->code >= 1000 && $this->code < 2000;
    }

    public function isPending(): bool
    {
        return $this->code === 1001;
    }

    public function hasMessage(): bool
    {
        return $this->code === 1301;
    }
}
