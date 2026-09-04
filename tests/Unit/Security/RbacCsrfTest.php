<?php
declare(strict_types=1);

namespace Tests\Unit\Security;

use Oshim\Testing\TestCase;
use Oshim\Security\Rbac;
use Oshim\Security\Policy;
use Oshim\Security\RateLimiter;
use Oshim\Security\Sanitizer;
use Oshim\Security\Csrf;

class InstancePolicy extends Policy
{
    public function reboot(object|array $user, object $instance): bool
    {
        $userId = is_object($user) ? ($user->id ?? null) : ($user['id'] ?? null);
        return $instance->user_id === $userId;
    }
}

class RbacCsrfTest extends TestCase
{
    public function testRbacRoleHierarchy(): void
    {
        $superadmin = (object)['role' => 'superadmin'];
        $admin = (object)['role' => 'admin'];
        $reseller = (object)['role' => 'reseller'];
        $client = (object)['role' => 'client'];
        $guest = (object)['role' => 'guest'];

        // Superadmin matches all role requirements
        $this->assertTrue(Rbac::hasRole($superadmin, 'admin'));
        $this->assertTrue(Rbac::hasRole($superadmin, 'client'));

        // Admin matches reseller and client
        $this->assertTrue(Rbac::hasRole($admin, 'admin'));
        $this->assertTrue(Rbac::hasRole($admin, 'reseller'));
        $this->assertTrue(Rbac::hasRole($admin, 'client'));
        $this->assertFalse(Rbac::hasRole($admin, 'superadmin'));

        // Client cannot access admin or reseller
        $this->assertTrue(Rbac::hasRole($client, 'client'));
        $this->assertFalse(Rbac::hasRole($client, 'admin'));
        $this->assertFalse(Rbac::hasRole($client, 'reseller'));
    }

    public function testRbacPolicyAndAbilityAuthorization(): void
    {
        Rbac::registerPolicy(\stdClass::class, InstancePolicy::class);

        $owner = (object)['id' => 10, 'role' => 'client'];
        $otherUser = (object)['id' => 20, 'role' => 'client'];
        $superAdmin = (object)['id' => 1, 'role' => 'superadmin'];

        $instance = (object)['user_id' => 10, 'name' => 'vps-node'];

        $this->assertTrue(Rbac::can($owner, 'reboot', $instance));
        $this->assertFalse(Rbac::can($otherUser, 'reboot', $instance));
        $this->assertTrue(Rbac::can($superAdmin, 'reboot', $instance)); // Superadmin bypass
    }

    public function testRateLimiterSlidingWindow(): void
    {
        $limiter = new RateLimiter();
        $limiter->clear();

        $key = 'api_client_192_168_1_1';
        $maxAttempts = 5;

        for ($i = 1; $i <= $maxAttempts; $i++) {
            $this->assertFalse($limiter->tooManyAttempts($key, $maxAttempts));
            $limiter->hit($key, 60);
        }

        // Limit reached
        $this->assertTrue($limiter->tooManyAttempts($key, $maxAttempts));
        $this->assertTrue($limiter->availableIn($key) > 0);

        // Reset
        $limiter->resetAttempts($key);
        $this->assertFalse($limiter->tooManyAttempts($key, $maxAttempts));
    }

    public function testSanitizerSecurityHelpers(): void
    {
        $dirtyHtml = "<script>alert('XSS')</script><b>Hello</b>";
        $escaped = Sanitizer::escapeHtml($dirtyHtml);
        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);

        $slug = Sanitizer::slug('OSHIM Cloud VPS Hosting #2026!');
        $this->assertEquals('oshim-cloud-vps-hosting-2026', $slug);
    }
}
