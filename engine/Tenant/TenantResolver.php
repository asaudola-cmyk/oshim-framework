<?php
declare(strict_types=1);

namespace Oshim\Tenant;

use Oshim\Http\Request;

class TenantResolver
{
    /**
     * Resolve tenant from request via Subdomain, Custom Domain, or Header (X-Tenant-ID).
     */
    public static function resolveFromRequest(Request $request, array $tenants = [], string $baseDomain = 'oshim.cloud'): ?Tenant
    {
        // 1. Header resolution
        $header = $request->header('x-tenant-id') ?? $request->header('X-Tenant-ID');
        if ($header !== null && isset($tenants[$header])) {
            return $tenants[$header];
        }

        // 2. Host resolution
        $host = $request->getHost();
        $host = strtolower(explode(':', $host)[0]);

        foreach ($tenants as $tenant) {
            // Check custom domain
            if ($tenant->customDomain !== null && $tenant->customDomain === $host) {
                return $tenant;
            }

            // Check subdomain (e.g. acme.oshim.cloud)
            $expectedSubdomain = $tenant->subdomain . '.' . $baseDomain;
            if ($host === $expectedSubdomain) {
                return $tenant;
            }
        }

        return null;
    }
}
