<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Auth\Password\PasswordHasher;
use Oshim\Auth\Guards\SessionGuard;
use Oshim\Auth\Guards\TokenGuard;
use Oshim\Auth\AuthManager;
use Oshim\Auth\Auth;
use Oshim\Http\Session\Session;
use Oshim\Http\Session\MemorySessionStore;
use Oshim\Http\Request;

final class AuthGuardTest extends TestCase
{
    public function testPasswordHasherArgon2id(): void
    {
        $password = 'SovereignPass123!#';
        $hash = PasswordHasher::make($password);

        $this->assertNotEmpty($hash);
        $this->assertTrue(PasswordHasher::check($password, $hash));
        $this->assertFalse(PasswordHasher::check('WrongPassword', $hash));
    }

    public function testSessionGuardLoginAndLogout(): void
    {
        $session = new Session(new MemorySessionStore(), 'test_app_key_32_chars_long_1234');
        $session->start();

        $guard = new SessionGuard($session);

        $this->assertFalse($guard->check());
        $this->assertTrue($guard->guest());

        $user = (object)['id' => 101, 'name' => 'Shafiullah', 'email' => 'ceo@oshim.cloud'];
        $guard->login($user);

        $this->assertTrue($guard->check());
        $this->assertFalse($guard->guest());
        $this->assertSame(101, $guard->id());
        $this->assertSame('Shafiullah', $guard->user()->name);

        $guard->logout();
        $this->assertFalse($guard->check());
        $this->assertTrue($guard->guest());
    }

    public function testTokenGuard(): void
    {
        $req = Request::create('/api/user', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer oshim_test_token_778899',
        ]);

        $guard = new TokenGuard($req, function ($token) {
            if ($token === 'oshim_test_token_778899') {
                return (object)['id' => 55, 'username' => 'api_admin'];
            }
            return null;
        });

        $this->assertTrue($guard->check());
        $this->assertSame(55, $guard->id());
        $this->assertSame('api_admin', $guard->user()->username);
    }
}
