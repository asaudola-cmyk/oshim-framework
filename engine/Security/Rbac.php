<?php
declare(strict_types=1);

namespace Oshim\Security;

use Oshim\Container\Container;

final class Rbac
{
    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_RESELLER = 'reseller';
    public const ROLE_CLIENT = 'client';
    public const ROLE_GUEST = 'guest';

    protected static array $roleHierarchy = [
        self::ROLE_SUPERADMIN => 100,
        self::ROLE_ADMIN      => 80,
        self::ROLE_RESELLER   => 50,
        self::ROLE_CLIENT     => 20,
        self::ROLE_GUEST      => 0,
    ];

    /** @var array<string, callable> */
    protected static array $abilities = [];
    /** @var array<string, string> */
    protected static array $policies = [];

    public static function getRoleLevel(string $role): int
    {
        return self::$roleHierarchy[strtolower($role)] ?? 0;
    }

    public static function hasRole(object|array $user, string|array $requiredRoles): bool
    {
        $userRole = is_object($user) ? ($user->role ?? self::ROLE_GUEST) : ($user['role'] ?? self::ROLE_GUEST);
        $userRole = strtolower((string)$userRole);

        // Super Admin has all roles inherently
        if ($userRole === self::ROLE_SUPERADMIN) {
            return true;
        }

        $roles = (array)$requiredRoles;

        foreach ($roles as $role) {
            $reqRole = strtolower(trim($role));
            // Exact match
            if ($userRole === $reqRole) {
                return true;
            }
            // Hierarchy check: user role level >= required role level
            if (self::getRoleLevel($userRole) >= self::getRoleLevel($reqRole) && self::getRoleLevel($reqRole) > 0) {
                return true;
            }
        }

        return false;
    }

    public static function define(string $permission, callable $callback): void
    {
        self::$abilities[$permission] = $callback;
    }

    public static function registerPolicy(string $modelClass, string $policyClass): void
    {
        self::$policies[$modelClass] = $policyClass;
    }

    public static function can(object|array $user, string $permission, mixed $resource = null): bool
    {
        // 1. Superadmin bypass
        if (self::hasRole($user, self::ROLE_SUPERADMIN)) {
            return true;
        }

        // 2. Check explicit abilities defined via define()
        if (isset(self::$abilities[$permission])) {
            return (bool)(self::$abilities[$permission])($user, $resource);
        }

        // 3. Check Policy class if resource is an object
        if (is_object($resource)) {
            $class = get_class($resource);
            if (isset(self::$policies[$class])) {
                $policyClass = self::$policies[$class];
                $policy = Container::getInstance()->make($policyClass);

                // Run before hook if present
                if (method_exists($policy, 'before')) {
                    $beforeResult = $policy->before($user, $permission);
                    if ($beforeResult !== null) {
                        return (bool)$beforeResult;
                    }
                }

                if (method_exists($policy, $permission)) {
                    return (bool)$policy->$permission($user, $resource);
                }
            }
        }

        // 4. Fallback: check user permissions array if available
        $permissions = is_object($user) ? ($user->permissions ?? []) : ($user['permissions'] ?? []);
        if (is_array($permissions) && (in_array('*', $permissions, true) || in_array($permission, $permissions, true))) {
            return true;
        }

        return false;
    }
}
