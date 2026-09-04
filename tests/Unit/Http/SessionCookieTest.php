<?php
declare(strict_types=1);

namespace Tests\Unit\Http;

use Oshim\Testing\TestCase;
use Oshim\Http\Cookie\Cookie;
use Oshim\Http\Session\Session;
use Oshim\Http\Session\EncryptedFileSessionStore;
use Oshim\Http\Session\SqliteSessionStore;
use PDO;

class SessionCookieTest extends TestCase
{
    protected string $appKey = 'oshim_test_app_key_32_bytes_12345';

    public function testCookieSerialization(): void
    {
        $cookie = Cookie::make(
            name: 'theme_pref',
            value: 'dark_glass',
            minutes: 60,
            path: '/client',
            secure: true,
            httpOnly: true,
            sameSite: 'Strict'
        );

        $header = $cookie->toHeaderString();

        $this->assertStringContainsString('theme_pref=dark_glass', $header);
        $this->assertStringContainsString('Path=/client', $header);
        $this->assertStringContainsString('Secure', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Strict', $header);
    }

    public function testCookieEncryptionAndDecryption(): void
    {
        $cookie = Cookie::make('secret_token', 'user_auth_payload_xyz');
        $encryptedCookie = $cookie->encrypt($this->appKey);

        $this->assertNotEquals('user_auth_payload_xyz', $encryptedCookie->getValue());
        $this->assertTrue($encryptedCookie->isEncrypted());

        $decrypted = $encryptedCookie->decrypt($this->appKey);
        $this->assertEquals('user_auth_payload_xyz', $decrypted);
    }

    public function testSessionLifecycleAndAttributes(): void
    {
        $storageDir = sys_get_temp_dir() . '/oshim_session_test_' . bin2hex(random_bytes(4));
        $store = new EncryptedFileSessionStore($storageDir, $this->appKey);
        $session = new Session($store, $this->appKey);

        $session->start();
        $this->assertTrue($session->isStarted());

        $session->set('user_id', 42);
        $session->set('role', 'admin');

        $this->assertEquals(42, $session->get('user_id'));
        $this->assertEquals('admin', $session->get('role'));
        $this->assertTrue($session->has('user_id'));

        $pulled = $session->pull('role');
        $this->assertEquals('admin', $pulled);
        $this->assertFalse($session->has('role'));

        $session->save();

        // Read session with fresh instance
        $newSession = new Session($store, $this->appKey);
        $newSession->start($session->getId());

        $this->assertEquals(42, $newSession->get('user_id'));
        $newSession->destroy();
    }

    public function testSessionFlashMessages(): void
    {
        $storageDir = sys_get_temp_dir() . '/oshim_session_flash_' . bin2hex(random_bytes(4));
        $store = new EncryptedFileSessionStore($storageDir, $this->appKey);
        $session = new Session($store, $this->appKey);

        $session->start();
        $session->flash('status', 'Order created successfully');
        $session->save();

        // Request 2: flash message should be available
        $sessionReq2 = new Session($store, $this->appKey);
        $sessionReq2->start($session->getId());
        $this->assertEquals('Order created successfully', $sessionReq2->get('status'));
        $sessionReq2->save();

        // Request 3: flash message should have expired
        $sessionReq3 = new Session($store, $this->appKey);
        $sessionReq3->start($session->getId());
        $this->assertNull($sessionReq3->get('status'));
        $sessionReq3->destroy();
    }

    public function testSqliteSessionStore(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new SqliteSessionStore($pdo, $this->appKey);
        $session = new Session($store, $this->appKey);

        $session->start();
        $session->set('currency', 'USD');
        $session->save();

        $verifySession = new Session($store, $this->appKey);
        $verifySession->start($session->getId());
        $this->assertEquals('USD', $verifySession->get('currency'));
        $verifySession->destroy();
    }
}
