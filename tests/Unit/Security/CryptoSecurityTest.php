<?php
declare(strict_types=1);

namespace Tests\Unit\Security;

use Oshim\Testing\TestCase;
use Oshim\Security\PasswordHasher;
use Oshim\Security\Ed25519Signer;
use Oshim\Security\TokenManager;
use Oshim\Security\Cipher;
use Oshim\Security\Exceptions\DecryptionException;

class CryptoSecurityTest extends TestCase
{
    public function testArgon2idPasswordHashingAndVerification(): void
    {
        $password = 'OshimCloud_SecurePass_2026!';
        $hash = PasswordHasher::hash($password);

        $this->assertNotEmpty($hash);
        $this->assertTrue(PasswordHasher::verify($password, $hash));
        $this->assertFalse(PasswordHasher::verify('WrongPassword123!', $hash));
    }

    public function testEd25519SignaturesAndVerification(): void
    {
        $keypair = Ed25519Signer::generateKeypair();
        $message = "COMMAND:create_instance:spec=vps_standard_4c8g:user=1042";

        $signature = Ed25519Signer::sign($message, $keypair['secretKey']);
        $this->assertNotEmpty($signature);

        // Verification with authentic public key
        $isValid = Ed25519Signer::verify($signature, $message, $keypair['publicKey']);
        $this->assertTrue($isValid);

        // Verification fails with tampered message
        $isTamperedValid = Ed25519Signer::verify($signature, $message . "_tampered", $keypair['publicKey']);
        $this->assertFalse($isTamperedValid);

        // Derived public key matches original
        $extractedPublic = Ed25519Signer::extractPublicKey($keypair['secretKey']);
        $this->assertEquals($keypair['publicKey'], $extractedPublic);
    }

    public function testStatelessTokenManagerLifecycle(): void
    {
        $keypair = Ed25519Signer::generateKeypair();
        $claims = [
            'sub'         => 'usr_998877',
            'role'        => 'admin',
            'permissions' => ['instances.reboot', 'invoices.view'],
        ];

        $token = TokenManager::issue($claims, $keypair['secretKey'], 3600);
        $this->assertStringStartsWith('oshim.', $token);

        $verified = TokenManager::verify($token, $keypair['publicKey']);
        $this->assertNotNull($verified);
        $this->assertEquals('usr_998877', $verified['sub']);
        $this->assertEquals('admin', $verified['role']);
        $this->assertEquals(['instances.reboot', 'invoices.view'], $verified['permissions']);

        // Expired token verification
        $expiredToken = TokenManager::issue($claims, $keypair['secretKey'], -100);
        $this->assertNull(TokenManager::verify($expiredToken, $keypair['publicKey']));
    }

    public function testAes256GcmAeadEncryptionAndTamperResistance(): void
    {
        $masterKey = Cipher::generateKey();
        $plaintext = "TOP_SECRET_CLUSTER_GATEWAY_TOKEN_990011";
        $aad = "TENANT_ID:42";

        $encrypted = Cipher::encrypt($plaintext, $masterKey, $aad);
        $this->assertNotEquals($plaintext, $encrypted);

        $decrypted = Cipher::decrypt($encrypted, $masterKey, $aad);
        $this->assertEquals($plaintext, $decrypted);

        // Tampered AAD must fail
        $this->assertThrows(function () use ($encrypted, $masterKey) {
            Cipher::decrypt($encrypted, $masterKey, "WRONG_TENANT_ID");
        }, DecryptionException::class);

        // Tampered ciphertext must fail
        $corrupted = substr($encrypted, 0, -4) . 'AAAA';
        $this->assertThrows(function () use ($corrupted, $masterKey, $aad) {
            Cipher::decrypt($corrupted, $masterKey, $aad);
        }, DecryptionException::class);
    }
}
