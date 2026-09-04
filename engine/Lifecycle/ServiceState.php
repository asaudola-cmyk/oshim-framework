<?php
declare(strict_types=1);

namespace Oshim\Lifecycle;

class ServiceState
{
    public const STATE_PENDING = 'PENDING';
    public const STATE_ACTIVE = 'ACTIVE';
    public const STATE_OVERDUE = 'OVERDUE';
    public const STATE_SUSPENDED = 'SUSPENDED';
    public const STATE_TERMINATED = 'TERMINATED';
    public const STATE_CANCELLED = 'CANCELLED';

    // Aliases
    public const PENDING = self::STATE_PENDING;
    public const ACTIVE = self::STATE_ACTIVE;
    public const OVERDUE = self::STATE_OVERDUE;
    public const SUSPENDED = self::STATE_SUSPENDED;
    public const TERMINATED = self::STATE_TERMINATED;
    public const CANCELLED = self::STATE_CANCELLED;

    public static function all(): array
    {
        return [
            self::STATE_PENDING,
            self::STATE_ACTIVE,
            self::STATE_OVERDUE,
            self::STATE_SUSPENDED,
            self::STATE_TERMINATED,
            self::STATE_CANCELLED,
        ];
    }
}
