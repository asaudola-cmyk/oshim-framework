<?php
declare(strict_types=1);

namespace Oshim\Http\Session;

use Oshim\Security\Cipher;

class EncryptedFileSessionStore implements SessionStoreInterface
{
    public function __construct(
        protected string $storagePath,
        protected string $appKey
    ) {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    protected function getFilePath(string $sessionId): string
    {
        // Sanitize session id to prevent path traversal
        $cleanId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sessionId) ?: 'invalid';
        return rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $cleanId;
    }

    public function read(string $sessionId): array
    {
        $filePath = $this->getFilePath($sessionId);
        if (!is_file($filePath)) {
            return [];
        }

        $raw = file_get_contents($filePath);
        if ($raw === false || $raw === '') {
            return [];
        }

        try {
            $decrypted = Cipher::decrypt($raw, $this->appKey, $sessionId);
            $data = unserialize($decrypted, ['allowed_classes' => true]);
            if (is_array($data)) {
                // Check timestamp expiry if stored
                if (isset($data['__expires_at']) && time() > $data['__expires_at']) {
                    $this->destroy($sessionId);
                    return [];
                }
                unset($data['__expires_at']);
                return $data;
            }
        } catch (\Throwable) {
            // Decryption failed or tampered
            return [];
        }

        return [];
    }

    public function write(string $sessionId, array $data, int $lifetimeSeconds): bool
    {
        $filePath = $this->getFilePath($sessionId);
        $tempPath = $filePath . '.' . bin2hex(random_bytes(6)) . '.tmp';

        $payloadData = $data;
        $payloadData['__expires_at'] = time() + $lifetimeSeconds;

        $serialized = serialize($payloadData);
        $encrypted = Cipher::encrypt($serialized, $this->appKey, $sessionId);

        // Atomic write via temp file + rename
        if (file_put_contents($tempPath, $encrypted) === false) {
            return false;
        }

        return rename($tempPath, $filePath);
    }

    public function destroy(string $sessionId): bool
    {
        $filePath = $this->getFilePath($sessionId);
        if (is_file($filePath)) {
            return unlink($filePath);
        }
        return true;
    }

    public function gc(int $maxLifetimeSeconds): int
    {
        $purged = 0;
        $files = glob(rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . 'sess_*');

        if ($files === false) {
            return 0;
        }

        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxLifetimeSeconds) {
                if (unlink($file)) {
                    $purged++;
                }
            }
        }

        return $purged;
    }
}
