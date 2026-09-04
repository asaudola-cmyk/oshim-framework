<?php
declare(strict_types=1);

namespace Oshim\Dns\Records;

/**
 * DNS Resource Record Type Constants and Classification (RFC 1035, RFC 3596, RFC 6844).
 */
final class RecordType
{
    public const A     = 1;     // Host address IPv4 (RFC 1035)
    public const NS    = 2;     // Authoritative name server (RFC 1035)
    public const CNAME = 5;     // Canonical name for an alias (RFC 1035)
    public const SOA   = 6;     // Start of a zone of authority (RFC 1035)
    public const PTR   = 12;    // Domain name pointer (RFC 1035)
    public const MX    = 15;    // Mail exchange (RFC 1035)
    public const TXT   = 16;    // Text strings (RFC 1035)
    public const AAAA  = 28;    // Host address IPv6 (RFC 3596)
    public const CAA   = 257;   // Certification Authority Authorization (RFC 6844/8659)
    public const AXFR  = 252;   // Zone transfer (RFC 1035)
    public const ANY   = 255;   // Match all records (RFC 1035)

    public const CLASS_IN  = 1;   // Internet
    public const CLASS_ANY = 255; // Any class

    private static array $nameMap = [
        self::A     => 'A',
        self::NS    => 'NS',
        self::CNAME => 'CNAME',
        self::SOA   => 'SOA',
        self::PTR   => 'PTR',
        self::MX    => 'MX',
        self::TXT   => 'TXT',
        self::AAAA  => 'AAAA',
        self::CAA   => 'CAA',
        self::AXFR  => 'AXFR',
        self::ANY   => 'ANY',
    ];

    private static array $typeMap = [
        'A'     => self::A,
        'NS'    => self::NS,
        'CNAME' => self::CNAME,
        'SOA'   => self::SOA,
        'PTR'   => self::PTR,
        'MX'    => self::MX,
        'TXT'   => self::TXT,
        'AAAA'  => self::AAAA,
        'CAA'   => self::CAA,
        'AXFR'  => self::AXFR,
        'ANY'   => self::ANY,
    ];

    public static function typeToName(int $type): string
    {
        return self::$nameMap[$type] ?? 'TYPE' . $type;
    }

    public static function nameToType(string|int $name): int
    {
        if (is_int($name)) {
            return $name;
        }
        $upper = strtoupper(trim($name));
        if (isset(self::$typeMap[$upper])) {
            return self::$typeMap[$upper];
        }
        if (is_numeric($name)) {
            return (int)$name;
        }
        return self::A;
    }
}
