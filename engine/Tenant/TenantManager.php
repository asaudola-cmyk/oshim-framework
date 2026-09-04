<?php
declare(strict_types=1);

namespace Oshim\Tenant;

use RuntimeException;

class TenantManager
{
    private static ?Tenant $currentTenant = null;
    /** @var array<string, Tenant> */
    private array $tenants = [];

    public function register(Tenant $tenant): void
    {
        $this->tenants[$tenant->id] = $tenant;
    }

    public function get(string $id): ?Tenant
    {
        return $this->tenants[$id] ?? null;
    }

    public function all(): array
    {
        return $this->tenants;
    }

    public static function current(): ?Tenant
    {
        return self::$currentTenant;
    }

    public static function check(): bool
    {
        return self::$currentTenant !== null;
    }

    public static function id(): ?string
    {
        return self::$currentTenant?->id;
    }

    public static function switch(?Tenant $tenant): void
    {
        self::$currentTenant = $tenant;
    }

    /**
     * Run a callback scoped within a specific tenant context and restore previous context.
     */
    public static function runInTenantContext(Tenant $tenant, callable $callback): mixed
    {
        $prev = self::$currentTenant;
        self::$currentTenant = $tenant;

        try {
            return $callback($tenant);
        } finally {
            self::$currentTenant = $prev;
        }
    }
}
