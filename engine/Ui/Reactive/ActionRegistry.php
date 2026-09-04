<?php
declare(strict_types=1);

namespace Oshim\Ui\Reactive;

use RuntimeException;

/**
 * Security Registry for Server Action validation and whitelist verification.
 */
class ActionRegistry
{
    /** @var array<string, array<string>> */
    private static array $allowedActions = [];

    /** @var array<string, bool> Blocked internal methods */
    private static array $blockedMethods = [
        '__construct' => true,
        '__destruct' => true,
        '__call' => true,
        '__get' => true,
        '__set' => true,
        'callAction' => true,
        'createSignedPayload' => true,
        'restoreFromSignedPayload' => true,
        'hydrate' => true,
        'getState' => true,
        'setSigningSecret' => true,
        'jsonSerialize' => true,
    ];

    /**
     * Register allowed actions for a component class.
     * @param class-string $class
     * @param array<string> $methods
     */
    public static function registerAllowed(string $class, array $methods): void
    {
        self::$allowedActions[$class] = $methods;
    }

    /**
     * Check if an action method is permitted to be invoked from the client.
     * @param class-string $class
     */
    public static function isActionAllowed(string $class, string $method): bool
    {
        if (isset(self::$blockedMethods[$method])) {
            return false;
        }

        if (isset(self::$allowedActions[$class])) {
            return in_array($method, self::$allowedActions[$class], true);
        }

        // By default verify method exists and is public
        if (class_exists($class)) {
            $ref = new \ReflectionClass($class);
            if ($ref->hasMethod($method)) {
                $m = $ref->getMethod($method);
                return $m->isPublic() && !$m->isStatic() && !isset(self::$blockedMethods[$method]);
            }
        }

        return false;
    }

    /**
     * Validate action execution security.
     * @param class-string $class
     */
    public static function assertActionAllowed(string $class, string $method): void
    {
        if (!self::isActionAllowed($class, $method)) {
            throw new RuntimeException("Unauthorized action method execution attempt: {$class}::{$method}");
        }
    }
}
