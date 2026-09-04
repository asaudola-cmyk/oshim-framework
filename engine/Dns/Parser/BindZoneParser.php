<?php
declare(strict_types=1);

namespace Oshim\Dns\Parser;

use Oshim\Dns\Exceptions\BindParseException;
use Oshim\Dns\Records\RecordType;
use Oshim\Dns\Records\ResourceRecord;
use Oshim\Dns\Zone\Zone;

/**
 * Standard RFC 1035 BIND Zone Master File Parser.
 */
class BindZoneParser
{
    /**
     * Parses a BIND zone file content string into a Zone model.
     *
     * @throws BindParseException On syntax errors.
     */
    public static function parse(string $content, string $defaultOrigin = ''): Zone
    {
        $currentOrigin = rtrim($defaultOrigin, '.');
        $defaultTtl = 3600;
        $records = [];
        $lastOwner = '@';

        // 1. Tokenize / collapse multiline parentheses while preserving quoted strings
        $lines = self::normalizeLines($content);

        foreach ($lines as $lineNum => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, ';')) {
                continue;
            }

            // Directives
            if (str_starts_with($trimmed, '$')) {
                $parts = preg_split('/\s+/', $trimmed, 3);
                $directive = strtoupper($parts[0]);

                if ($directive === '$ORIGIN') {
                    if (empty($parts[1])) {
                        throw new BindParseException("Missing argument for \$ORIGIN directive", $lineNum);
                    }
                    $currentOrigin = rtrim($parts[1], '.');
                } elseif ($directive === '$TTL') {
                    if (empty($parts[1])) {
                        throw new BindParseException("Missing argument for \$TTL directive", $lineNum);
                    }
                    $defaultTtl = self::parseTtl($parts[1]);
                }
                continue;
            }

            // Record parsing
            $tokens = self::tokenizeLine($line);
            if (empty($tokens)) {
                continue;
            }

            // Check if owner is omitted (leading whitespace)
            $hasLeadingWs = (strlen($line) > 0 && ($line[0] === ' ' || $line[0] === "\t"));
            $tokenIdx = 0;

            if ($hasLeadingWs) {
                $owner = $lastOwner;
            } else {
                $owner = $tokens[$tokenIdx++];
                $lastOwner = $owner;
            }

            // Normalize owner
            $canonicalOwner = self::normalizeDomain($owner, $currentOrigin);

            // Optional TTL
            $ttl = $defaultTtl;
            if (isset($tokens[$tokenIdx]) && (is_numeric($tokens[$tokenIdx]) || preg_match('/^\d+[smhdw]$/i', $tokens[$tokenIdx]))) {
                $ttl = self::parseTtl($tokens[$tokenIdx++]);
            }

            // Optional Class (IN, CH, etc.)
            if (isset($tokens[$tokenIdx]) && strtoupper($tokens[$tokenIdx]) === 'IN') {
                $tokenIdx++;
            }

            // Type
            if (!isset($tokens[$tokenIdx])) {
                throw new BindParseException("Missing record type", $lineNum);
            }
            $typeStr = strtoupper($tokens[$tokenIdx++]);
            $type = RecordType::nameToType($typeStr);

            // Remaining tokens are RDATA
            $rdataTokens = array_slice($tokens, $tokenIdx);
            $rdata = self::parseRdata($type, $rdataTokens, $currentOrigin, $lineNum);

            $records[] = new ResourceRecord($canonicalOwner, $type, $rdata, $ttl);
        }

        $zoneName = $currentOrigin ?: ($records[0] ? $records[0]->getName() : 'example.com');
        $zone = new Zone($zoneName, $defaultTtl, 1, $records);

        // Update zone serial from SOA if present
        $soa = $zone->getSoaRecord();
        if ($soa !== null && is_array($soa->getData()) && isset($soa->getData()['serial'])) {
            $zone->setSerial((int)$soa->getData()['serial']);
        }

        return $zone;
    }

    public static function parseFile(string $filePath, string $defaultOrigin = ''): Zone
    {
        if (!is_file($filePath)) {
            throw new BindParseException("Zone file not found: {$filePath}");
        }
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new BindParseException("Failed to read zone file: {$filePath}");
        }
        return self::parse($content, $defaultOrigin);
    }

    public static function parseTtl(string $ttlStr): int
    {
        $ttlStr = trim($ttlStr);
        if (is_numeric($ttlStr)) {
            return (int)$ttlStr;
        }

        if (preg_match('/^(\d+)([smhdw])$/i', $ttlStr, $m)) {
            $val = (int)$m[1];
            $unit = strtolower($m[2]);
            return match ($unit) {
                's' => $val,
                'm' => $val * 60,
                'h' => $val * 3600,
                'd' => $val * 86400,
                'w' => $val * 604800,
                default => $val,
            };
        }

        return 3600;
    }

    private static function normalizeDomain(string $name, string $origin): string
    {
        $name = trim($name);
        if ($name === '@' || $name === '') {
            return $origin;
        }
        if (str_ends_with($name, '.')) {
            return rtrim($name, '.');
        }
        return $origin !== '' ? $name . '.' . $origin : $name;
    }

    /**
     * Collapses multiline parentheses into single lines and strips non-quoted comments.
     *
     * @return array<int, string>
     */
    private static function normalizeLines(string $content): array
    {
        $rawLines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
        $normalized = [];
        $inParen = false;
        $parenAccumulator = '';
        $startLineNum = 1;

        foreach ($rawLines as $idx => $line) {
            $lineNum = $idx + 1;

            // Strip unquoted comments
            $cleanLine = self::stripComments($line);

            if (!$inParen) {
                if (str_contains($cleanLine, '(')) {
                    $inParen = true;
                    $startLineNum = $lineNum;
                    $parenAccumulator = preg_replace('/\(/', ' ', $cleanLine, 1);
                    if (str_contains($parenAccumulator, ')')) {
                        $inParen = false;
                        $normalized[$startLineNum] = str_replace(')', ' ', $parenAccumulator);
                        $parenAccumulator = '';
                    }
                } else {
                    $normalized[$lineNum] = $cleanLine;
                }
            } else {
                if (str_contains($cleanLine, ')')) {
                    $inParen = false;
                    $parenAccumulator .= ' ' . preg_replace('/\)/', ' ', $cleanLine, 1);
                    $normalized[$startLineNum] = $parenAccumulator;
                    $parenAccumulator = '';
                } else {
                    $parenAccumulator .= ' ' . $cleanLine;
                }
            }
        }

        return $normalized;
    }

    /**
     * Strips semicolons outside quoted strings.
     */
    private static function stripComments(string $line): string
    {
        $len = strlen($line);
        $inQuote = false;
        $result = '';

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];

            if ($char === '"' && ($i === 0 || $line[$i - 1] !== '\\')) {
                $inQuote = !$inQuote;
                $result .= $char;
                continue;
            }

            if ($char === ';' && !$inQuote) {
                break; // Comment starts here
            }

            $result .= $char;
        }

        return $result;
    }

    /**
     * Tokenizes a line into elements while preserving quoted strings.
     *
     * @return list<string>
     */
    private static function tokenizeLine(string $line): array
    {
        $tokens = [];
        $len = strlen($line);
        $inQuote = false;
        $current = '';

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];

            if ($char === '"' && ($i === 0 || $line[$i - 1] !== '\\')) {
                $inQuote = !$inQuote;
                $current .= $char;
                continue;
            }

            if (($char === ' ' || $char === "\t") && !$inQuote) {
                if ($current !== '') {
                    $tokens[] = self::cleanToken($current);
                    $current = '';
                }
                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $tokens[] = self::cleanToken($current);
        }

        return $tokens;
    }

    private static function cleanToken(string $token): string
    {
        if (str_starts_with($token, '"') && str_ends_with($token, '"') && strlen($token) >= 2) {
            return substr($token, 1, -1);
        }
        return $token;
    }

    private static function parseRdata(int $type, array $tokens, string $origin, int $lineNum): mixed
    {
        switch ($type) {
            case RecordType::A:
            case RecordType::AAAA:
                if (empty($tokens)) {
                    throw new BindParseException("Missing IP address", $lineNum);
                }
                return $tokens[0];

            case RecordType::CNAME:
            case RecordType::NS:
            case RecordType::PTR:
                if (empty($tokens)) {
                    throw new BindParseException("Missing target host", $lineNum);
                }
                return self::normalizeDomain($tokens[0], $origin);

            case RecordType::MX:
                if (count($tokens) < 2) {
                    throw new BindParseException("MX record requires preference and exchange", $lineNum);
                }
                return [
                    'preference' => (int)$tokens[0],
                    'exchange' => self::normalizeDomain($tokens[1], $origin),
                ];

            case RecordType::TXT:
                return implode(' ', $tokens);

            case RecordType::SOA:
                if (count($tokens) < 7) {
                    throw new BindParseException("SOA record requires MNAME, RNAME, and 5 timer values", $lineNum);
                }
                return [
                    'mname' => self::normalizeDomain($tokens[0], $origin),
                    'rname' => self::normalizeDomain($tokens[1], $origin),
                    'serial' => (int)$tokens[2],
                    'refresh' => self::parseTtl($tokens[3]),
                    'retry' => self::parseTtl($tokens[4]),
                    'expire' => self::parseTtl($tokens[5]),
                    'minimum' => self::parseTtl($tokens[6]),
                ];

            case RecordType::CAA:
                if (count($tokens) < 3) {
                    throw new BindParseException("CAA record requires flags, tag, and value", $lineNum);
                }
                return [
                    'flags' => (int)$tokens[0],
                    'tag' => $tokens[1],
                    'value' => implode(' ', array_slice($tokens, 2)),
                ];

            default:
                return implode(' ', $tokens);
        }
    }
}
