<?php
declare(strict_types=1);

namespace Oshim\Epp\Model;

/**
 * Result DTO for Domain Availability Check (RFC 5731 §3.1.1).
 */
class DomainCheckResult
{
    private string $domain;
    private bool $available;
    private ?string $reason;

    public function __construct(string $domain, bool $available, ?string $reason = null)
    {
        $this->domain = strtolower(trim($domain));
        $this->available = $available;
        $this->reason = $reason;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'available' => $this->available,
            'reason' => $this->reason,
        ];
    }
}
