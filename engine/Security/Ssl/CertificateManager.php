<?php
declare(strict_types=1);

namespace Oshim\Security\Ssl;

class CertificateManager
{
    private static array $certificates = [];

    public static function issue(string $domain, string $email = 'admin@oshim.cloud', array $san = []): array
    {
        $client = new AcmeV2Client($email);
        $certData = $client->requestCertificate($domain, $san);
        self::$certificates[$domain] = $certData;
        return $certData;
    }

    public static function get(string $domain): ?array
    {
        return self::$certificates[$domain] ?? null;
    }

    public static function isExpiringSoon(string $domain, int $daysThreshold = 15): bool
    {
        $cert = self::get($domain);
        if (!$cert) {
            return true;
        }
        $validTo = strtotime($cert['valid_to']);
        return ($validTo - time()) <= ($daysThreshold * 86400);
    }

    public static function renewAll(): array
    {
        $renewed = [];
        foreach (self::$certificates as $domain => $cert) {
            if (self::isExpiringSoon($domain)) {
                $renewed[$domain] = self::issue($domain);
            }
        }
        return $renewed;
    }

    public static function all(): array
    {
        return self::$certificates;
    }
}
