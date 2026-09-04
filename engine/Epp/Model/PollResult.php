<?php
declare(strict_types=1);

namespace Oshim\Epp\Model;

/**
 * Result DTO for EPP Message Queue Poll (RFC 5730 §2.9.2.3).
 */
class PollResult
{
    private bool $hasMessage;
    private int $count;
    private ?string $msgId;
    private ?string $enqueueDate;
    private ?string $message;
    private ?string $resDataXml;

    public function __construct(
        bool $hasMessage,
        int $count = 0,
        ?string $msgId = null,
        ?string $enqueueDate = null,
        ?string $message = null,
        ?string $resDataXml = null
    ) {
        $this->hasMessage = $hasMessage;
        $this->count = $count;
        $this->msgId = $msgId;
        $this->enqueueDate = $enqueueDate;
        $this->message = $message;
        $this->resDataXml = $resDataXml;
    }

    public function hasMessage(): bool
    {
        return $this->hasMessage;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getMsgId(): ?string
    {
        return $this->msgId;
    }

    public function getEnqueueDate(): ?string
    {
        return $this->enqueueDate;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getResDataXml(): ?string
    {
        return $this->resDataXml;
    }
}
