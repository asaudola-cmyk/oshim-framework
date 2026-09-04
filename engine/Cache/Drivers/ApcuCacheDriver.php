<?php
declare(strict_types=1);

namespace Oshim\Cache\Drivers;

use RuntimeException;
use DateInterval;
use DateTime;

class ApcuCacheDriver implements CacheDriverInterface
{
    public function __construct(protected string $prefix = 'oshim_')
    {
        if (!function_exists('apcu_fetch') || !ini_get('apc.enabled')) {
            // Log instead of throwing to allow CLI commands to run if apc.enable_cli is off
            error_log("APCu extension is not loaded or not enabled.");
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!function_exists('apcu_fetch')) return $default;
        $success = false;
        $value = apcu_fetch($this->prefix . $key, $success);
        return $success ? $value : $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        if (!function_exists('apcu_store')) return false;
        $seconds = $this->getSeconds($ttl);
        return apcu_store($this->prefix . $key, $value, $seconds);
    }

    public function delete(string $key): bool
    {
        if (!function_exists('apcu_delete')) return false;
        return apcu_delete($this->prefix . $key);
    }

    public function clear(): bool
    {
        if (!function_exists('apcu_clear_cache')) return false;
        if (class_exists(\APCIterator::class)) {
            $iterator = new \APCIterator('user', '/^' . preg_quote($this->prefix, '/') . '/');
            return apcu_delete($iterator);
        }
        return apcu_clear_cache();
    }

    public function has(string $key): bool
    {
        if (!function_exists('apcu_exists')) return false;
        return apcu_exists($this->prefix . $key);
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $val = $this->get($key);
        if ($val !== null && $val !== false) return $val;
        
        $val = $callback();
        $this->set($key, $val, $ttlSeconds);
        return $val;
    }

    public function increment(string $key, int $value = 1): int
    {
        if (!function_exists('apcu_inc')) return $value;
        $success = false;
        $newVal = apcu_inc($this->prefix . $key, $value, $success);
        if (!$success) {
            $this->set($key, $value);
            return $value;
        }
        return $newVal;
    }

    public function decrement(string $key, int $value = 1): int
    {
        if (!function_exists('apcu_dec')) return -$value;
        $success = false;
        $newVal = apcu_dec($this->prefix . $key, $value, $success);
        if (!$success) {
            $this->set($key, -$value);
            return -$value;
        }
        return $newVal;
    }

    protected function getSeconds(DateInterval|int|null $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }
        if (is_int($ttl)) {
            return $ttl;
        }
        return (new DateTime())->add($ttl)->getTimestamp() - time();
    }
}
