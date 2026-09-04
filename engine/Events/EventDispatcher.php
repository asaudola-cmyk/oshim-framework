<?php
declare(strict_types=1);

namespace Oshim\Events;

use Oshim\Container\Container;

/**
 * 👑 Sovereign Event Dispatcher (Pub/Sub)
 * 
 * WHY: Decouples application logic. Allows modules to communicate without hard dependencies.
 */
class EventDispatcher
{
    /**
     * @var array<string, array<callable|string|array>>
     */
    protected static array $listeners = [];

    /**
     * Register a listener for an event.
     */
    public static function listen(string $event, callable|string|array $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    /**
     * Dispatch an event and trigger all registered listeners.
     */
    public static function dispatch(string|object $event, mixed $payload = null): void
    {
        $eventName = is_object($event) ? get_class($event) : $event;
        $eventPayload = is_object($event) ? $event : $payload;

        if (!isset(self::$listeners[$eventName])) {
            return;
        }

        foreach (self::$listeners[$eventName] as $listener) {
            $result = self::resolveAndCall($listener, $eventPayload);
            
            // Edge Case: If a listener returns explicitly false, halt propagation
            if ($result === false) {
                break;
            }
        }
    }

    /**
     * Resolves the listener dynamically using the DI Container.
     * WHY: Prevents memory bloat by only instantiating listener classes when the event fires.
     */
    protected static function resolveAndCall(callable|string|array $listener, mixed $payload): mixed
    {
        if (is_callable($listener)) {
            return $listener($payload);
        }

        if (is_string($listener)) {
            // Assume it's a class string with a 'handle' method
            $instance = Container::getInstance()->make($listener);
            return $instance->handle($payload);
        }

        if (is_array($listener) && count($listener) === 2) {
            [$class, $method] = $listener;
            $instance = is_string($class) ? Container::getInstance()->make($class) : $class;
            return $instance->$method($payload);
        }

        return null;
    }

    /**
     * Clear all registered listeners (Useful for testing)
     */
    public static function clear(): void
    {
        self::$listeners = [];
    }
}
