<?php
declare(strict_types=1);

namespace Oshim\Dns\Records\Codec;

use Oshim\Dns\Exceptions\DnsParseException;
use Oshim\Dns\Exceptions\InvalidRecordException;
use Oshim\Dns\Records\RecordType;
use Oshim\Dns\Wire\DnsCodec;

/**
 * Binary RDATA Encoders and Decoders for all 9 Standard DNS Resource Record Types (RFC 1035, 3596, 6844).
 */
class RecordDataCodec
{
    /**
     * Encodes RDATA into wire format.
     */
    public static function encode(int $type, mixed $data, array &$offsetMap = [], int $currentOffset = 0): string
    {
        switch ($type) {
            case RecordType::A:
                $ip = is_array($data) ? ($data['value'] ?? $data['address'] ?? '127.0.0.1') : (string)$data;
                $packed = @inet_pton($ip);
                if ($packed === false || strlen($packed) !== 4) {
                    throw new InvalidRecordException("Invalid IPv4 address for A record: " . $ip);
                }
                return $packed;

            case RecordType::AAAA:
                $ipv6 = is_array($data) ? ($data['value'] ?? $data['address'] ?? '::1') : (string)$data;
                $packed = @inet_pton($ipv6);
                if ($packed === false || strlen($packed) !== 16) {
                    throw new InvalidRecordException("Invalid IPv6 address for AAAA record: " . $ipv6);
                }
                return $packed;

            case RecordType::CNAME:
            case RecordType::NS:
            case RecordType::PTR:
                $target = is_array($data) ? ($data['target'] ?? $data['value'] ?? $data['host'] ?? '') : (string)$data;
                return DnsCodec::encodeDomainName($target, $offsetMap, $currentOffset);

            case RecordType::MX:
                $pref = is_array($data) ? (int)($data['preference'] ?? $data['priority'] ?? 10) : 10;
                $exchange = is_array($data) ? (string)($data['exchange'] ?? $data['value'] ?? '') : (string)$data;
                $prefBytes = pack('n', $pref);
                $exchBytes = DnsCodec::encodeDomainName($exchange, $offsetMap, $currentOffset + 2);
                return $prefBytes . $exchBytes;

            case RecordType::TXT:
                $strings = is_array($data) ? ($data['text'] ?? $data['value'] ?? $data) : [$data];
                if (!is_array($strings)) {
                    $strings = [(string)$strings];
                }
                $rdata = '';
                foreach ($strings as $str) {
                    $str = (string)$str;
                    if ($str === '') {
                        $rdata .= "\x00";
                        continue;
                    }
                    $chunks = str_split($str, 255);
                    foreach ($chunks as $chunk) {
                        $rdata .= chr(strlen($chunk)) . $chunk;
                    }
                }
                return $rdata;

            case RecordType::SOA:
                $mname = is_array($data) ? (string)($data['mname'] ?? '') : '';
                $rname = is_array($data) ? (string)($data['rname'] ?? '') : '';
                $serial = is_array($data) ? (int)($data['serial'] ?? time()) : time();
                $refresh = is_array($data) ? (int)($data['refresh'] ?? 3600) : 3600;
                $retry = is_array($data) ? (int)($data['retry'] ?? 1800) : 1800;
                $expire = is_array($data) ? (int)($data['expire'] ?? 604800) : 604800;
                $minimum = is_array($data) ? (int)($data['minimum'] ?? 86400) : 86400;

                $mnameBytes = DnsCodec::encodeDomainName($mname, $offsetMap, $currentOffset);
                $rnameBytes = DnsCodec::encodeDomainName($rname, $offsetMap, $currentOffset + strlen($mnameBytes));
                $timers = pack('NNNNN', $serial, $refresh, $retry, $expire, $minimum);

                return $mnameBytes . $rnameBytes . $timers;

            case RecordType::CAA:
                $flags = is_array($data) ? (int)($data['flags'] ?? 0) : 0;
                $tag = is_array($data) ? (string)($data['tag'] ?? 'issue') : 'issue';
                $value = is_array($data) ? (string)($data['value'] ?? '') : '';

                $tagLen = strlen($tag);
                if ($tagLen > 255) {
                    throw new InvalidRecordException("CAA tag length exceeds 255 octets.");
                }

                return chr($flags) . chr($tagLen) . $tag . $value;

            default:
                return is_string($data) ? $data : '';
        }
    }

    /**
     * Decodes RDATA from wire buffer.
     */
    public static function decode(int $type, string $wire, int $offset, int $length): mixed
    {
        $wireLen = strlen($wire);
        if ($offset + $length > $wireLen) {
            throw new DnsParseException(
                sprintf("RDATA length exceeds buffer (%d + %d > %d).", $offset, $length, $wireLen)
            );
        }

        $rdata = substr($wire, $offset, $length);

        switch ($type) {
            case RecordType::A:
                if ($length !== 4) {
                    throw new DnsParseException("Invalid length for A record RDATA (" . $length . " bytes, expected 4)");
                }
                $ip = inet_ntop($rdata);
                if ($ip === false) {
                    throw new DnsParseException("Failed to decode IPv4 address from A record RDATA");
                }
                return $ip;

            case RecordType::AAAA:
                if ($length !== 16) {
                    throw new DnsParseException("Invalid length for AAAA record RDATA (" . $length . " bytes, expected 16)");
                }
                $ipv6 = inet_ntop($rdata);
                if ($ipv6 === false) {
                    throw new DnsParseException("Failed to decode IPv6 address from AAAA record RDATA");
                }
                return $ipv6;

            case RecordType::CNAME:
            case RecordType::NS:
            case RecordType::PTR:
                $cur = $offset;
                return DnsCodec::decodeDomainName($wire, $cur);

            case RecordType::MX:
                if ($length < 2) {
                    throw new DnsParseException("MX record RDATA too short (minimum 2 bytes required for preference)");
                }
                $pref = unpack('n', substr($rdata, 0, 2))[1];
                $cur = $offset + 2;
                $exchange = DnsCodec::decodeDomainName($wire, $cur);
                return [
                    'preference' => $pref,
                    'exchange' => $exchange,
                ];

            case RecordType::TXT:
                $strings = [];
                $cur = 0;
                while ($cur < $length) {
                    $strLen = ord($rdata[$cur]);
                    $cur++;
                    if ($cur + $strLen > $length) {
                        throw new DnsParseException("TXT character-string length exceeds RDATA boundary.");
                    }
                    $strings[] = substr($rdata, $cur, $strLen);
                    $cur += $strLen;
                }
                return implode('', $strings);

            case RecordType::SOA:
                $cur = $offset;
                $mname = DnsCodec::decodeDomainName($wire, $cur);
                $rname = DnsCodec::decodeDomainName($wire, $cur);

                if ($cur + 20 > $offset + $length) {
                    throw new DnsParseException("SOA record RDATA missing 20-byte timer sequence.");
                }

                $timers = unpack('Nserial/Nrefresh/Nretry/Nexpire/Nminimum', substr($wire, $cur, 20));
                return [
                    'mname' => $mname,
                    'rname' => $rname,
                    'serial' => $timers['serial'],
                    'refresh' => $timers['refresh'],
                    'retry' => $timers['retry'],
                    'expire' => $timers['expire'],
                    'minimum' => $timers['minimum'],
                ];

            case RecordType::CAA:
                if ($length < 2) {
                    throw new DnsParseException("CAA record RDATA too short (minimum 2 bytes required)");
                }
                $flags = ord($rdata[0]);
                $tagLen = ord($rdata[1]);
                if (2 + $tagLen > $length) {
                    throw new DnsParseException("CAA tag length exceeds RDATA boundary.");
                }
                $tag = substr($rdata, 2, $tagLen);
                $value = substr($rdata, 2 + $tagLen);
                return [
                    'flags' => $flags,
                    'tag' => $tag,
                    'value' => $value,
                ];

            default:
                return $rdata;
        }
    }
}
