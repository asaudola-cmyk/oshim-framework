<?php
declare(strict_types=1);

namespace Oshim\Dns\Packet;

use Oshim\Dns\Exceptions\DnsParseException;
use Oshim\Dns\Records\Codec\RecordDataCodec;
use Oshim\Dns\Records\ResourceRecord;
use Oshim\Dns\Wire\DnsCodec;
use Oshim\Dns\Wire\DnsHeader;
use Oshim\Dns\Wire\DnsQuestion;

/**
 * Complete DNS Wire Packet Parser, Serializer, and Aggregate (RFC 1035 §4.1).
 */
class DnsPacket
{
    public DnsHeader $header;
    /** @var list<DnsQuestion> */
    public array $questions = [];
    /** @var list<ResourceRecord> */
    public array $answers = [];
    /** @var list<ResourceRecord> */
    public array $authorities = [];
    /** @var list<ResourceRecord> */
    public array $additionals = [];

    public function __construct(
        ?DnsHeader $header = null,
        array $questions = [],
        array $answers = [],
        array $authorities = [],
        array $additionals = []
    ) {
        $this->header = $header ?? new DnsHeader();
        $this->questions = $questions;
        $this->answers = $answers;
        $this->authorities = $authorities;
        $this->additionals = $additionals;
    }

    public function addQuestion(DnsQuestion $question): self
    {
        $this->questions[] = $question;
        return $this;
    }

    public function addAnswer(ResourceRecord $rr): self
    {
        $this->answers[] = $rr;
        return $this;
    }

    public function addAuthority(ResourceRecord $rr): self
    {
        $this->authorities[] = $rr;
        return $this;
    }

    public function addAdditional(ResourceRecord $rr): self
    {
        $this->additionals[] = $rr;
        return $this;
    }

    public function getHeader(): DnsHeader
    {
        return $this->header;
    }

    /**
     * @return list<DnsQuestion>
     */
    public function getQuestions(): array
    {
        return $this->questions;
    }

    /**
     * @return list<ResourceRecord>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /**
     * @return list<ResourceRecord>
     */
    public function getAuthorities(): array
    {
        return $this->authorities;
    }

    /**
     * @return list<ResourceRecord>
     */
    public function getAdditionals(): array
    {
        return $this->additionals;
    }

    /**
     * Serializes the DNS packet into wire binary with UDP truncation enforcement.
     */
    public function serialize(int $maxBytes = 0): string
    {
        $this->header->qdCount = count($this->questions);
        $this->header->anCount = count($this->answers);
        $this->header->nsCount = count($this->authorities);
        $this->header->arCount = count($this->additionals);

        $offsetMap = [];
        $wire = $this->header->pack(); // 12 bytes

        // Questions
        foreach ($this->questions as $q) {
            $wire .= $q->pack($offsetMap, strlen($wire));
        }

        // Answers
        foreach ($this->answers as $rr) {
            $wire .= self::serializeResourceRecord($rr, $offsetMap, strlen($wire));
        }

        // Authorities
        foreach ($this->authorities as $rr) {
            $wire .= self::serializeResourceRecord($rr, $offsetMap, strlen($wire));
        }

        // Additionals
        foreach ($this->additionals as $rr) {
            $wire .= self::serializeResourceRecord($rr, $offsetMap, strlen($wire));
        }

        // Truncation check
        if ($maxBytes > 0 && strlen($wire) > $maxBytes) {
            $truncatedHeader = clone $this->header;
            $truncatedHeader->tc = true;
            $truncatedHeader->anCount = 0;
            $truncatedHeader->nsCount = 0;
            $truncatedHeader->arCount = 0;

            $offsetMapTrunc = [];
            $truncWire = $truncatedHeader->pack();
            foreach ($this->questions as $q) {
                $truncWire .= $q->pack($offsetMapTrunc, strlen($truncWire));
            }

            return $truncWire;
        }

        return $wire;
    }

    public function pack(int $maxBytes = 0): string
    {
        return $this->serialize($maxBytes);
    }

    /**
     * Parses binary wire data into a DnsPacket object.
     *
     * @throws DnsParseException If the packet is malformed.
     */
    public static function parse(string $wire): self
    {
        if (strlen($wire) < 12) {
            throw new DnsParseException("Buffer too short for DNS packet (" . strlen($wire) . " bytes)");
        }

        $header = DnsHeader::unpack(substr($wire, 0, 12));
        $offset = 12;

        // Questions
        $questions = [];
        for ($i = 0; $i < $header->qdCount; $i++) {
            $questions[] = DnsQuestion::unpack($wire, $offset);
        }

        // Answers
        $answers = [];
        for ($i = 0; $i < $header->anCount; $i++) {
            $answers[] = self::parseResourceRecord($wire, $offset);
        }

        // Authorities
        $authorities = [];
        for ($i = 0; $i < $header->nsCount; $i++) {
            $authorities[] = self::parseResourceRecord($wire, $offset);
        }

        // Additionals
        $additionals = [];
        for ($i = 0; $i < $header->arCount; $i++) {
            $additionals[] = self::parseResourceRecord($wire, $offset);
        }

        return new self($header, $questions, $answers, $authorities, $additionals);
    }

    public static function unpack(string $wire): self
    {
        return self::parse($wire);
    }

    private static function serializeResourceRecord(ResourceRecord $rr, array &$offsetMap, int $currentOffset): string
    {
        $nameBytes = DnsCodec::encodeDomainName($rr->getName(), $offsetMap, $currentOffset);
        $rdataOffset = $currentOffset + strlen($nameBytes) + 10; // 10 bytes for type, class, ttl, rdLength
        $rdataBytes = RecordDataCodec::encode($rr->getType(), $rr->getData(), $offsetMap, $rdataOffset);
        $rdLength = strlen($rdataBytes);

        $fixedHeader = pack('nnNn', $rr->getType(), $rr->getClass(), $rr->getTtl(), $rdLength);
        return $nameBytes . $fixedHeader . $rdataBytes;
    }

    private static function parseResourceRecord(string $wire, int &$offset): ResourceRecord
    {
        $name = DnsCodec::decodeDomainName($wire, $offset);

        if ($offset + 10 > strlen($wire)) {
            throw new DnsParseException("Unexpected EOF while reading Resource Record header.");
        }

        $fields = unpack('ntype/nclass/Nttl/nrdLength', substr($wire, $offset, 10));
        $offset += 10;

        $type = $fields['type'];
        $class = $fields['class'];
        $ttl = $fields['ttl'];
        $rdLength = $fields['rdLength'];

        if ($offset + $rdLength > strlen($wire)) {
            throw new DnsParseException("Unexpected EOF while reading Resource Record RDATA.");
        }

        $data = RecordDataCodec::decode($type, $wire, $offset, $rdLength);
        $offset += $rdLength;

        return new ResourceRecord($name, $type, $data, $ttl, $class);
    }
}
