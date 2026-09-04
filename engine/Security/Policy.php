<?php
declare(strict_types=1);

namespace Oshim\Security;

abstract class Policy
{
    /**
     * Pre-authorization hook.
     * Return true to allow, false to deny, or null to continue to policy method.
     */
    public function before(object|array $user, string $ability): ?bool
    {
        if (Rbac::hasRole($user, Rbac::ROLE_SUPERADMIN)) {
            return true;
        }
        return null;
    }
}
