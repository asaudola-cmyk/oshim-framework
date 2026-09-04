<?php
declare(strict_types=1);

namespace Oshim\Dns\Wire;

use Oshim\Dns\Exceptions\DnsParseException;

/**
 * RFC 1035 §4.1.1 12-Byte Fixed Header Codec and Model.
 */
class DnsHeader
{
    public const RCODE_NOERROR  = 0;
    public const RCODE_FORMERR  = 1;
    public const RCODE_SERVFAIL = 2;
    public const RCODE_NXDOMAIN = 3;
    public const RCODE_NOTIMP   = 4;
    public const RCODE_REFUSED  = 5;

    public const OPCODE_QUERY  = 0;
    public const OPCODE_IQUERY = 1;
    public const OPCODE_STATUS = 2;

    public int $id = 0;
    public bool $qr = false;
    public int $opcode = self::OPCODE_QUERY;
    public bool $aa = false;
    public bool $tc = false;
    public bool $rd = false;
    public bool $ra = false;
    public int $z = 0;
    public int $rcode = self::RCODE_NOERROR;
    public int $qdCount = 0;
    public int $anCount = 0;
    public int $nsCount = 0;
    public int $arCount = 0;

    public function __construct(
        int $id = 0,
        bool $qr = false,
        int $opcode = self::OPCODE_QUERY,
        bool $aa = false,
        bool $tc = false,
        bool $rd = false,
        bool $ra = false,
        int $rcode = self::RCODE_NOERROR,
        int $qdCount = 0,
        int $anCount = 0,
        int $nsCount = 0,
        int $arCount = 0
    ) {
        $this->id = $id;
        $this->qr = $qr;
        $this->opcode = $opcode;
        $this->aa = $aa;
        $this->tc = $tc;
        $this->rd = $rd;
        $this->ra = $ra;
        $this->rcode = $rcode;
        $this->qdCount = $qdCount;
        $this->anCount = $anCount;
        $this->nsCount = $nsCount;
        $this->arCount = $arCount;
    }

    public function pack(): string
    {
        $flags = 0;
        if ($this->qr) {
            $flags |= (1 << 15);
        }
        $flags |= (($this->opcode & 0x0F) << 11);
        if ($this->aa) {
            $flags |= (1 << 10);
        }
        if ($this->tc) {
            $flags |= (1 << 9);
        }
        if ($this->rd) {
            $flags |= (1 << 8);
        }
        if ($this->ra) {
            $flags |= (1 << 7);
        }
        $flags |= (($this->z & 0x07) << 4);
        $flags |= ($this->rcode & 0x0F);

        return pack('nnnnnn', $this->id, $flags, $this->qdCount, $this->anCount, $this->nsCount, $this->arCount);
    }

    public static function unpack(string $data): self
    {
        if (strlen($data) < 12) {
            throw new DnsParseException(
                sprintf("Buffer too short for DNS header (%d bytes, 12 expected).", strlen($data))
            );
        }

        $fields = unpack('nid/nflags/nqdCount/nanCount/nnsCount/narCount', substr($data, 0, 12));
        $header = new self();
        $header->id = $fields['id'];
        $flags = $fields['flags'];

        $header->qr     = (bool)(($flags >> 15) & 1);
        $header->opcode = ($flags >> 11) & 0x0F;
        $header->aa     = (bool)(($flags >> 10) & 1);
        $header->tc     = (bool)(($flags >> 9) & 1);
        $header->rd     = (bool)(($flags >> 8) & 1);
        $header->ra     = (bool)(($flags >> 7) & 1);
        $header->z      = ($flags >> 4) & 0x07;
        $header->rcode  = $flags & 0x0F;

        $header->qdCount = $fields['qdCount'];
        $header->anCount = $fields['anCount'];
        $header->nsCount = $fields['nsCount'];
        $header->arCount = $fields['arCount'];

        return $header;
    }

    public function isResponse(): bool
    {
        return $this->qr;
    }

    public function isAuthoritative(): bool
    {
        return $this->aa;
    }

    public function isTruncated(): bool
    {
        return $this->tc;
    }

    public function getRcode(): int
    {
        return $this->rcode;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
