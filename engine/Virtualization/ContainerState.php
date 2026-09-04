<?php
declare(strict_types=1);

namespace Oshim\Virtualization;

/**
 * Lifecycle states for virtual container instances.
 */
final class ContainerState
{
    public const CREATED   = 'CREATED';
    public const RUNNING   = 'RUNNING';
    public const PAUSED    = 'PAUSED';
    public const STOPPED   = 'STOPPED';
    public const SUSPENDED = 'SUSPENDED';
    public const DESTROYED = 'DESTROYED';
    public const ERROR     = 'ERROR';

    /**
     * Get all valid container states.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CREATED,
            self::RUNNING,
            self::PAUSED,
            self::STOPPED,
            self::SUSPENDED,
            self::DESTROYED,
            self::ERROR,
        ];
    }

    /**
     * Check if a state string is valid.
     */
    public static function isValid(string $state): bool
    {
        return in_array(strtoupper($state), self::all(), true);
    }

    /**
     * Check if a state is considered active/running.
     */
    public static function isActive(string $state): bool
    {
        return strtoupper($state) === self::RUNNING;
    }

    /**
     * Check if a state is terminal.
     */
    public static function isTerminal(string $state): bool
    {
        return in_array(strtoupper($state), [self::DESTROYED, self::ERROR], true);
    }
}
