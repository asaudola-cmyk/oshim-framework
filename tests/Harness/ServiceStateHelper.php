<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use InvalidArgumentException;

class ServiceStateHelper
{
    public const STATE_PENDING = 'PENDING';
    public const STATE_ACTIVE = 'ACTIVE';
    public const STATE_OVERDUE = 'OVERDUE';
    public const STATE_SUSPENDED = 'SUSPENDED';
    public const STATE_TERMINATED = 'TERMINATED';

    private string $state;
    private array $history = [];

    public function __construct(string $initialState = self::STATE_PENDING)
    {
        $this->state = $initialState;
        $this->history[] = ['from' => null, 'to' => $initialState, 'action' => 'init', 'timestamp' => time()];
    }

    public function getState(): string { return $this->state; }
    public function getHistory(): array { return $this->history; }

    public function transition(string $action): string
    {
        $oldState = $this->state;
        $newState = match ($this->state) {
            self::STATE_PENDING => match ($action) {
                'pay_invoice' => self::STATE_ACTIVE,
                'cancel_order' => self::STATE_TERMINATED,
                default => throw new InvalidArgumentException("Cannot {$action} in {$this->state}"),
            },
            self::STATE_ACTIVE => match ($action) {
                'pass_due_date' => self::STATE_OVERDUE,
                'cancel' => self::STATE_SUSPENDED,
                default => throw new InvalidArgumentException("Cannot {$action} in {$this->state}"),
            },
            self::STATE_OVERDUE => match ($action) {
                'pay_invoice' => self::STATE_ACTIVE,
                'expire_grace_period' => self::STATE_SUSPENDED,
                default => throw new InvalidArgumentException("Cannot {$action} in {$this->state}"),
            },
            self::STATE_SUSPENDED => match ($action) {
                'pay_overdue_invoice' => self::STATE_ACTIVE,
                'expire_retention_period' => self::STATE_TERMINATED,
                default => throw new InvalidArgumentException("Cannot {$action} in {$this->state}"),
            },
            self::STATE_TERMINATED => throw new InvalidArgumentException("Cannot transition from TERMINATED state"),
            default => throw new InvalidArgumentException("Unknown state: {$this->state}"),
        };

        $this->state = $newState;
        $this->history[] = ['from' => $oldState, 'to' => $newState, 'action' => $action, 'timestamp' => time()];
        return $this->state;
    }
}
