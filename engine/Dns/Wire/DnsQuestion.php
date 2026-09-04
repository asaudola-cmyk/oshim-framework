<?php
declare(strict_types=1);

namespace Oshim\Dns\Wire;

use Oshim\Dns\Exceptions\DnsParseException;
use Oshim\Dns\Records\RecordType;

/**
 * RFC 1035 §4.1.2 DNS Question Section Entity.
 */
class DnsQuestion
{
    private string $name;
    private int $type;
    private int $class;

    public function __construct(string $name, int|string $type = RecordType::A, int $class = RecordType::CLASS_IN)
    {
        $this->name = strtolower(trim($name, '.'));
        $this->type = is_int($type) ? $type : RecordType::nameToType($type);
        $this->class = $class;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getTypeName(): string
    {
        return RecordType::typeToName($this->type);
    }

    public function getClass(): int
    {
        return $this->class;
    }

    /**
     * Packs the question into wire binary format.
     */
    public function pack(array &$offsetMap = [], int $currentOffset = 12): string
    {
        $qname = DnsCodec::encodeDomainName($this->name, $offsetMap, $currentOffset);
        return $qname . pack('nn', $this->type, $this->class);
    }

    /**
     * Unpacks a question from wire binary data at offset.
     */
    public static function unpack(string $wire, int &$offset): self
    {
        $name = DnsCodec::decodeDomainName($wire, $offset);

        if ($offset + 4 > strlen($wire)) {
            throw new DnsParseException("Unexpected EOF while reading DNS Question QTYPE and QCLASS.");
        }

        $fields = unpack('ntype/nclass', substr($wire, $offset, 4));
        $offset += 4;

        return new self($name, $fields['type'], $fields['class']);
    }
}
