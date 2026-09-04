<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use RuntimeException;

/**
 * RFC 1035 UDP/TCP DNS wire packet generator, parser, and query client supporting all 9 standard records.
 */
class MockDnsClient
{
    public const TYPE_A = 1;
    public const TYPE_NS = 2;
    public const TYPE_CNAME = 5;
    public const TYPE_SOA = 6;
    public const TYPE_PTR = 12;
    public const TYPE_MX = 15;
    public const TYPE_TXT = 16;
    public const TYPE_AAAA = 28;
    public const TYPE_CAA = 257;

    public const CLASS_IN = 1;

    public function query(
        string $domain,
        string $type = 'A',
        string $protocol = 'udp',
        string $serverHost = '127.0.0.1',
        int $serverPort = 5353,
        float $timeout = 2.0
    ): DnsWireResponse {
        $packet = $this->buildQueryPacket($domain, $type);

        if (strtolower($protocol) === 'tcp') {
            $responseBytes = $this->sendTcp($serverHost, $serverPort, $packet, $timeout);
        } else {
            $responseBytes = $this->sendUdp($serverHost, $serverPort, $packet, $timeout);
        }

        return DnsWireResponse::parse($responseBytes);
    }

    public function buildQueryPacket(string $domain, string $type = 'A', int $id = 0): string
    {
        if ($id === 0) {
            $id = random_int(1, 65535);
        }

        // Header: ID (16b), Flags (0x0100 -> QR=0, Opcode=0, RD=1), QDCOUNT=1, ANCOUNT=0, NSCOUNT=0, ARCOUNT=0
        $flags = 0x0100;
        $header = pack('n6', $id, $flags, 1, 0, 0, 0);

        // Question: QNAME, QTYPE (16b), QCLASS (16b)
        $qname = self::encodeDomainName($domain);
        $typeId = self::typeNameToId($type);
        $question = $qname . pack('nn', $typeId, self::CLASS_IN);

        return $header . $question;
    }

    public function buildResponsePacket(
        int $id,
        string $domain,
        string $type,
        array $records,
        bool $isAuthoritative = true,
        int $rCode = 0,
        int $ttl = 300
    ): string {
        $flags = 0x8000; // QR=1 (response)
        if ($isAuthoritative) {
            $flags |= 0x0400; // AA=1
        }
        $flags |= 0x0100; // RD=1
        $flags |= 0x0080; // RA=1
        $flags |= ($rCode & 0xF);

        $anCount = count($records);
        $header = pack('n6', $id, $flags, 1, $anCount, 0, 0);

        $qname = self::encodeDomainName($domain);
        $typeId = self::typeNameToId($type);
        $question = $qname . pack('nn', $typeId, self::CLASS_IN);

        $answers = '';
        foreach ($records as $record) {
            $rdata = self::encodeRData($type, $record);
            $rdLength = strlen($rdata);
            $answers .= $qname . pack('nnNn', $typeId, self::CLASS_IN, $ttl, $rdLength) . $rdata;
        }

        return $header . $question . $answers;
    }

    public static function encodeDomainName(string $domain): string
    {
        $domain = trim($domain, '.');
        if ($domain === '') {
            return "\x00";
        }

        $wire = '';
        foreach (explode('.', $domain) as $label) {
            $len = strlen($label);
            $wire .= chr($len) . $label;
        }
        return $wire . "\x00";
    }

    public static function decodeDomainName(string $wire, int &$offset): string
    {
        $labels = [];
        $jumped = false;
        $originalOffset = $offset;
        $guard = 0;

        while ($offset < strlen($wire) && $guard++ < 128) {
            $len = ord($wire[$offset]);
            if ($len === 0) {
                $offset++;
                break;
            }

            // Pointer check: 2 MSB bits set (0xC0)
            if (($len & 0xC0) === 0xC0) {
                if ($offset + 2 > strlen($wire)) {
                    break;
                }
                $ptr = unpack('n', substr($wire, $offset, 2))[1] & 0x3FFF;
                if (!$jumped) {
                    $originalOffset = $offset + 2;
                    $jumped = true;
                }
                $offset = $ptr;
                continue;
            }

            $offset++;
            if ($offset + $len > strlen($wire)) {
                break;
            }
            $labels[] = substr($wire, $offset, $len);
            $offset += $len;
        }

        if ($jumped) {
            $offset = $originalOffset;
        }

        return implode('.', $labels);
    }

    public static function encodeRData(string $type, mixed $data): string
    {
        $typeUpper = strtoupper($type);
        switch ($typeUpper) {
            case 'A':
                $val = is_array($data) ? ($data['value'] ?? $data['address'] ?? '127.0.0.1') : (string)$data;
                $packed = inet_pton($val);
                return $packed !== false ? $packed : inet_pton('127.0.0.1');

            case 'AAAA':
                $val = is_array($data) ? ($data['value'] ?? $data['address'] ?? '::1') : (string)$data;
                $packed = inet_pton($val);
                return $packed !== false ? $packed : inet_pton('::1');

            case 'CNAME':
            case 'NS':
            case 'PTR':
                $val = is_array($data) ? ($data['value'] ?? $data['target'] ?? $data['host'] ?? '') : (string)$data;
                return self::encodeDomainName($val);

            case 'MX':
                $pref = is_array($data) ? (int)($data['preference'] ?? $data['priority'] ?? 10) : 10;
                $exchange = is_array($data) ? (string)($data['exchange'] ?? $data['value'] ?? '') : (string)$data;
                return pack('n', $pref) . self::encodeDomainName($exchange);

            case 'TXT':
                $val = is_array($data) ? (string)($data['value'] ?? $data['text'] ?? '') : (string)$data;
                return chr(strlen($val)) . $val;

            case 'SOA':
                $mname = is_array($data) ? (string)($data['mname'] ?? 'ns1.example.com') : 'ns1.example.com';
                $rname = is_array($data) ? (string)($data['rname'] ?? 'admin.example.com') : 'admin.example.com';
                $serial = is_array($data) ? (int)($data['serial'] ?? time()) : time();
                $refresh = is_array($data) ? (int)($data['refresh'] ?? 7200) : 7200;
                $retry = is_array($data) ? (int)($data['retry'] ?? 3600) : 3600;
                $expire = is_array($data) ? (int)($data['expire'] ?? 1209600) : 1209600;
                $minimum = is_array($data) ? (int)($data['minimum'] ?? 300) : 300;
                return self::encodeDomainName($mname) . self::encodeDomainName($rname) . pack('N5', $serial, $refresh, $retry, $expire, $minimum);

            case 'CAA':
                $flags = is_array($data) ? (int)($data['flags'] ?? 0) : 0;
                $tag = is_array($data) ? (string)($data['tag'] ?? 'issue') : 'issue';
                $val = is_array($data) ? (string)($data['value'] ?? '') : (string)$data;
                return chr($flags) . chr(strlen($tag)) . $tag . $val;

            default:
                return is_string($data) ? $data : '';
        }
    }

    public static function decodeRData(string $typeName, int $typeId, string $wire, int $offset, int $length): mixed
    {
        $rdata = substr($wire, $offset, $length);
        $typeUpper = strtoupper($typeName);

        switch ($typeUpper) {
            case 'A':
                return ['value' => inet_ntop($rdata), 'address' => inet_ntop($rdata)];

            case 'AAAA':
                return ['value' => inet_ntop($rdata), 'address' => inet_ntop($rdata)];

            case 'CNAME':
            case 'NS':
            case 'PTR':
                $cur = $offset;
                $decoded = self::decodeDomainName($wire, $cur);
                return ['value' => $decoded, 'target' => $decoded];

            case 'MX':
                if ($length >= 2) {
                    $pref = unpack('n', substr($rdata, 0, 2))[1];
                    $cur = $offset + 2;
                    $exchange = self::decodeDomainName($wire, $cur);
                    return ['preference' => $pref, 'priority' => $pref, 'exchange' => $exchange, 'value' => $exchange];
                }
                return ['value' => ''];

            case 'TXT':
                $txts = [];
                $cur = 0;
                while ($cur < $length) {
                    $txtLen = ord($rdata[$cur]);
                    $cur++;
                    $txts[] = substr($rdata, $cur, $txtLen);
                    $cur += $txtLen;
                }
                $joined = implode('', $txts);
                return ['value' => $joined, 'text' => $joined];

            case 'SOA':
                $cur = $offset;
                $mname = self::decodeDomainName($wire, $cur);
                $rname = self::decodeDomainName($wire, $cur);
                $soapart = substr($wire, $cur, 20);
                if (strlen($soapart) >= 20) {
                    $timers = unpack('NSerial/NRefresh/NRetry/NExpire/NMinimum', $soapart);
                    return [
                        'mname' => $mname,
                        'rname' => $rname,
                        'serial' => $timers['Serial'],
                        'refresh' => $timers['Refresh'],
                        'retry' => $timers['Retry'],
                        'expire' => $timers['Expire'],
                        'minimum' => $timers['Minimum'],
                        'value' => $mname,
                    ];
                }
                return ['mname' => $mname, 'rname' => $rname, 'value' => $mname];

            case 'CAA':
                if ($length >= 2) {
                    $flags = ord($rdata[0]);
                    $tagLen = ord($rdata[1]);
                    $tag = substr($rdata, 2, $tagLen);
                    $val = substr($rdata, 2 + $tagLen);
                    return ['flags' => $flags, 'tag' => $tag, 'value' => $val];
                }
                return ['value' => $rdata];

            default:
                return ['value' => $rdata];
        }
    }

    public static function typeNameToId(string|int $name): int
    {
        if (is_int($name)) {
            return $name;
        }
        $map = [
            'A' => self::TYPE_A,
            'NS' => self::TYPE_NS,
            'CNAME' => self::TYPE_CNAME,
            'SOA' => self::TYPE_SOA,
            'PTR' => self::TYPE_PTR,
            'MX' => self::TYPE_MX,
            'TXT' => self::TYPE_TXT,
            'AAAA' => self::TYPE_AAAA,
            'CAA' => self::TYPE_CAA,
        ];
        $upper = strtoupper(trim((string)$name));
        if (isset($map[$upper])) {
            return $map[$upper];
        }
        if (is_numeric($name)) {
            return (int)$name;
        }
        return 1;
    }

    public static function typeIdToName(int $id): string
    {
        $map = [
            self::TYPE_A => 'A',
            self::TYPE_NS => 'NS',
            self::TYPE_CNAME => 'CNAME',
            self::TYPE_SOA => 'SOA',
            self::TYPE_PTR => 'PTR',
            self::TYPE_MX => 'MX',
            self::TYPE_TXT => 'TXT',
            self::TYPE_AAAA => 'AAAA',
            self::TYPE_CAA => 'CAA',
        ];
        return $map[$id] ?? 'TYPE' . $id;
    }

    private function sendUdp(string $host, int $port, string $packet, float $timeout = 2.0): string
    {
        $socket = @stream_socket_client("udp://{$host}:{$port}", $errno, $errstr, $timeout);
        if (!$socket) {
            throw new RuntimeException("Failed to connect UDP socket to {$host}:{$port}: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, (int)$timeout, (int)(($timeout - (int)$timeout) * 1e6));
        fwrite($socket, $packet);

        $response = fread($socket, 4096);
        fclose($socket);

        if ($response === false || $response === '') {
            throw new RuntimeException("DNS UDP query timed out or received empty response from {$host}:{$port}");
        }

        return $response;
    }

    private function sendTcp(string $host, int $port, string $packet, float $timeout = 2.0): string
    {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
        if (!$socket) {
            throw new RuntimeException("Failed to connect TCP socket to {$host}:{$port}: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, (int)$timeout, (int)(($timeout - (int)$timeout) * 1e6));

        // TCP prefix length (2 bytes)
        $framed = pack('n', strlen($packet)) . $packet;
        fwrite($socket, $framed);

        $hdr = fread($socket, 2);
        if ($hdr === false || strlen($hdr) < 2) {
            fclose($socket);
            throw new RuntimeException("Failed to read TCP DNS response length prefix");
        }

        $length = unpack('n', $hdr)[1];
        $payload = '';
        $remaining = $length;
        while ($remaining > 0 && !feof($socket)) {
            $chunk = fread($socket, min($remaining, 4096));
            if ($chunk === false || $chunk === '') break;
            $payload .= $chunk;
            $remaining -= strlen($chunk);
        }

        fclose($socket);
        return $payload;
    }
}
