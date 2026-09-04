<?php
declare(strict_types=1);

namespace Oshim\Dns\Wire;

use Oshim\Dns\Exceptions\DnsParseException;
use Oshim\Dns\Exceptions\InvalidRecordException;

/**
 * Binary Domain Name Encoder and Decoder with RFC 1035 §4.1.4 Pointer Compression and Cyclic Loop Protection.
 */
class DnsCodec
{
    public const MAX_LABEL_LENGTH = 63;
    public const MAX_NAME_LENGTH = 255;
    public const MAX_POINTER_HOPS = 16;

    /**
     * Encodes a human-readable domain name into wire format with optional pointer compression.
     *
     * @param array<string, int> $offsetMap Map of lowercase domain suffix => wire byte offset.
     * @throws InvalidRecordException If label or total name length exceeds RFC limits.
     */
    public static function encodeDomainName(string $domain, array &$offsetMap = [], int $currentOffset = 0): string
    {
        $domain = trim($domain, '.');
        if ($domain === '' || $domain === '@') {
            return "\x00";
        }

        $labels = explode('.', $domain);
        $encoded = '';
        $count = count($labels);

        for ($i = 0; $i < $count; $i++) {
            $suffix = strtolower(implode('.', array_slice($labels, $i)));

            // Check if suffix can be compressed via pointer
            if (isset($offsetMap[$suffix])) {
                $ptr = 0xC000 | ($offsetMap[$suffix] & 0x3FFF);
                $encoded .= pack('n', $ptr);
                return $encoded;
            }

            $label = $labels[$i];
            $len = strlen($label);

            if ($len > self::MAX_LABEL_LENGTH) {
                throw new InvalidRecordException(
                    sprintf("DNS label '%s' exceeds maximum length of %d octets (has %d).", $label, self::MAX_LABEL_LENGTH, $len)
                );
            }

            if ($len === 0) {
                continue;
            }

            // Record offset for compression of this suffix
            $offsetMap[$suffix] = $currentOffset + strlen($encoded);
            $encoded .= chr($len) . $label;
        }

        $encoded .= "\x00";

        if (strlen($encoded) > self::MAX_NAME_LENGTH) {
            throw new InvalidRecordException(
                sprintf("DNS domain name '%s' exceeds maximum length of %d octets.", $domain, self::MAX_NAME_LENGTH)
            );
        }

        return $encoded;
    }

    /**
     * Decodes a binary wire domain name starting at $offset, tracking pointers with cycle protection.
     *
     * @throws DnsParseException On EOF, out-of-bounds offset, label overflow, or cyclic pointer loops.
     */
    public static function decodeDomainName(string $wire, int &$offset): string
    {
        $wireLen = strlen($wire);
        $labels = [];
        $jumped = false;
        $originalOffset = $offset;
        $visited = [];
        $hops = 0;

        while ($offset < $wireLen) {
            $len = ord($wire[$offset]);

            // Null byte terminates the domain name
            if ($len === 0) {
                $offset++;
                break;
            }

            // Pointer detected: top 2 bits set (0xC000)
            if (($len & 0xC0) === 0xC0) {
                if ($offset + 1 >= $wireLen) {
                    throw new DnsParseException("Unexpected EOF while reading DNS compression pointer.");
                }

                $ptr = unpack('n', substr($wire, $offset, 2))[1] & 0x3FFF;

                if ($ptr >= $wireLen) {
                    throw new DnsParseException(
                        sprintf("DNS compression pointer out of bounds (offset %d, wire length %d).", $ptr, $wireLen)
                    );
                }

                if (isset($visited[$ptr])) {
                    throw new DnsParseException("Cyclic DNS compression pointer loop detected at offset " . $ptr);
                }

                $hops++;
                if ($hops > self::MAX_POINTER_HOPS) {
                    throw new DnsParseException("Excessive DNS compression pointer jumps (> " . self::MAX_POINTER_HOPS . ").");
                }

                $visited[$ptr] = true;

                if (!$jumped) {
                    $originalOffset = $offset + 2;
                    $jumped = true;
                }

                $offset = $ptr;
                continue;
            }

            // Standard label
            if ($len > self::MAX_LABEL_LENGTH) {
                throw new DnsParseException(
                    sprintf("DNS label length %d exceeds maximum allowed %d.", $len, self::MAX_LABEL_LENGTH)
                );
            }

            $offset++;
            if ($offset + $len > $wireLen) {
                throw new DnsParseException("Unexpected EOF while reading DNS label content.");
            }

            $labels[] = substr($wire, $offset, $len);
            $offset += $len;
        }

        if ($jumped) {
            $offset = $originalOffset;
        }

        return implode('.', $labels);
    }
}
