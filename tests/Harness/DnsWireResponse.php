<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

/**
 * Parsed DNS wire response with rich assertions for DNS protocol validation.
 */
class DnsWireResponse
{
    private string $rawData;
    private int $id;
    private int $flags;
    private bool $isResponse;
    private bool $isAuthoritative;
    private bool $isTruncated;
    private bool $recursionDesired;
    private bool $recursionAvailable;
    private int $rCode;
    private int $qdCount;
    private int $anCount;
    private int $nsCount;
    private int $arCount;
    private array $questions = [];
    private array $answers = [];
    private array $authorities = [];
    private array $additionals = [];

    public function __construct(
        string $rawData,
        int $id,
        int $flags,
        bool $isResponse,
        bool $isAuthoritative,
        bool $isTruncated,
        bool $recursionDesired,
        bool $recursionAvailable,
        int $rCode,
        int $qdCount,
        int $anCount,
        int $nsCount,
        int $arCount,
        array $questions = [],
        array $answers = [],
        array $authorities = [],
        array $additionals = []
    ) {
        $this->rawData = $rawData;
        $this->id = $id;
        $this->flags = $flags;
        $this->isResponse = $isResponse;
        $this->isAuthoritative = $isAuthoritative;
        $this->isTruncated = $isTruncated;
        $this->recursionDesired = $recursionDesired;
        $this->recursionAvailable = $recursionAvailable;
        $this->rCode = $rCode;
        $this->qdCount = $qdCount;
        $this->anCount = $anCount;
        $this->nsCount = $nsCount;
        $this->arCount = $arCount;
        $this->questions = $questions;
        $this->answers = $answers;
        $this->authorities = $authorities;
        $this->additionals = $additionals;
    }

    public static function parse(string $wire): self
    {
        if (strlen($wire) < 12) {
            throw new AssertionException("DNS wire response is too short (< 12 bytes header). Length: " . strlen($wire));
        }

        $header = unpack('nID/nFlags/nQDCount/nANCount/nNSCount/nARCount', substr($wire, 0, 12));
        $id = $header['ID'];
        $flags = $header['Flags'];
        $isResponse = (($flags >> 15) & 0x1) === 1;
        $isAuthoritative = (($flags >> 10) & 0x1) === 1;
        $isTruncated = (($flags >> 9) & 0x1) === 1;
        $recursionDesired = (($flags >> 8) & 0x1) === 1;
        $recursionAvailable = (($flags >> 7) & 0x1) === 1;
        $rCode = $flags & 0xF;

        $qdCount = $header['QDCount'];
        $anCount = $header['ANCount'];
        $nsCount = $header['NSCount'];
        $arCount = $header['ARCount'];

        $offset = 12;
        $questions = [];
        for ($i = 0; $i < $qdCount; $i++) {
            $qname = MockDnsClient::decodeDomainName($wire, $offset);
            $qparams = unpack('nType/nClass', substr($wire, $offset, 4));
            $offset += 4;
            $questions[] = [
                'name' => $qname,
                'type' => MockDnsClient::typeIdToName($qparams['Type']),
                'type_id' => $qparams['Type'],
                'class' => $qparams['Class'],
            ];
        }

        $answers = self::parseRecords($wire, $offset, $anCount);
        $authorities = self::parseRecords($wire, $offset, $nsCount);
        $additionals = self::parseRecords($wire, $offset, $arCount);

        return new self(
            $wire,
            $id,
            $flags,
            $isResponse,
            $isAuthoritative,
            $isTruncated,
            $recursionDesired,
            $recursionAvailable,
            $rCode,
            $qdCount,
            $anCount,
            $nsCount,
            $arCount,
            $questions,
            $answers,
            $authorities,
            $additionals
        );
    }

    private static function parseRecords(string $wire, int &$offset, int $count): array
    {
        $records = [];
        for ($i = 0; $i < $count && $offset < strlen($wire); $i++) {
            $name = MockDnsClient::decodeDomainName($wire, $offset);
            if ($offset + 10 > strlen($wire)) {
                break;
            }

            $fields = unpack('nType/nClass/NTTL/nRDLength', substr($wire, $offset, 10));
            $offset += 10;

            $typeId = $fields['Type'];
            $typeName = MockDnsClient::typeIdToName($typeId);
            $ttl = $fields['TTL'];
            $rdLength = $fields['RDLength'];
            $rdataBytes = substr($wire, $offset, $rdLength);
            $rdataOffset = $offset;

            $decodedData = MockDnsClient::decodeRData($typeName, $typeId, $wire, $rdataOffset, $rdLength);
            $offset += $rdLength;

            $records[] = [
                'name' => $name,
                'type' => $typeName,
                'type_id' => $typeId,
                'class' => $fields['Class'],
                'ttl' => $ttl,
                'data' => $decodedData,
            ];
        }
        return $records;
    }

    public function assertNoError(string $message = ''): self
    {
        Assert::recordAssertion();
        if ($this->rCode !== 0) {
            throw new AssertionException(
                $message ?: "Expected DNS RCODE 0 (NoError), but got {$this->rCode}."
            );
        }
        return $this;
    }

    public function assertNxDomain(string $message = ''): self
    {
        Assert::recordAssertion();
        if ($this->rCode !== 3) {
            throw new AssertionException(
                $message ?: "Expected DNS RCODE 3 (NXDomain), but got {$this->rCode}."
            );
        }
        return $this;
    }

    public function assertServFail(string $message = ''): self
    {
        Assert::recordAssertion();
        if ($this->rCode !== 2) {
            throw new AssertionException(
                $message ?: "Expected DNS RCODE 2 (ServFail), but got {$this->rCode}."
            );
        }
        return $this;
    }

    public function assertAuthoritative(string $message = ''): self
    {
        Assert::recordAssertion();
        if (!$this->isAuthoritative) {
            throw new AssertionException(
                $message ?: "Expected DNS response to have Authoritative Answer (AA) bit set."
            );
        }
        return $this;
    }

    public function assertRecordCount(int $count, ?string $type = null, string $message = ''): self
    {
        Assert::recordAssertion();
        $target = $this->answers;
        if ($type !== null) {
            $target = array_filter($target, fn($r) => strtoupper($r['type']) === strtoupper($type));
        }

        $actual = count($target);
        if ($actual !== $count) {
            $typeStr = $type ? " of type {$type}" : '';
            throw new AssertionException(
                $message ?: "Expected {$count} DNS answer records{$typeStr}, but found {$actual}."
            );
        }
        return $this;
    }

    public function assertHasRecord(string $type, mixed $value = null, array $attributes = [], string $message = ''): self
    {
        Assert::recordAssertion();
        $typeUpper = strtoupper($type);
        $found = false;

        foreach ($this->answers as $record) {
            if (strtoupper($record['type']) !== $typeUpper) {
                continue;
            }

            if ($value === null) {
                $found = true;
                break;
            }

            $recData = $record['data'];
            if (is_array($recData)) {
                $matchesValue = (isset($recData['value']) && $recData['value'] == $value)
                    || (isset($recData['address']) && $recData['address'] == $value)
                    || (isset($recData['target']) && $recData['target'] == $value)
                    || (isset($recData['exchange']) && $recData['exchange'] == $value)
                    || (isset($recData['mname']) && $recData['mname'] == $value);

                if ($matchesValue) {
                    $attrMatch = true;
                    foreach ($attributes as $k => $v) {
                        if (!isset($recData[$k]) || $recData[$k] != $v) {
                            $attrMatch = false;
                            break;
                        }
                    }
                    if ($attrMatch) {
                        $found = true;
                        break;
                    }
                }
            } elseif ($recData == $value) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $valStr = $value !== null ? " with value '" . (is_array($value) ? json_encode($value) : $value) . "'" : '';
            $ansStr = json_encode($this->answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new AssertionException(
                $message ?: "Expected DNS answer to contain record {$typeUpper}{$valStr}.\nActual answers:\n{$ansStr}"
            );
        }
        return $this;
    }

    public function assertHasNoRecord(string $type, mixed $value = null, string $message = ''): self
    {
        Assert::recordAssertion();
        $typeUpper = strtoupper($type);
        foreach ($this->answers as $record) {
            if (strtoupper($record['type']) === $typeUpper) {
                if ($value === null || $record['data'] == $value) {
                    throw new AssertionException(
                        $message ?: "Found unexpected DNS record of type {$typeUpper} in answers."
                    );
                }
            }
        }
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRCode(): int
    {
        return $this->rCode;
    }

    public function isAuthoritative(): bool
    {
        return $this->isAuthoritative;
    }

    public function isTruncated(): bool
    {
        return $this->isTruncated;
    }

    public function isResponse(): bool
    {
        return $this->isResponse;
    }

    public function getQuestions(): array
    {
        return $this->questions;
    }

    public function getAnswers(): array
    {
        return $this->answers;
    }

    public function getAuthorities(): array
    {
        return $this->authorities;
    }

    public function getAdditionals(): array
    {
        return $this->additionals;
    }

    public function getRawData(): string
    {
        return $this->rawData;
    }
}
