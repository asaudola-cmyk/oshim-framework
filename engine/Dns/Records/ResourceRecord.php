<?php
declare(strict_types=1);

namespace Oshim\Dns\Records;

/**
 * Unified DNS Resource Record (RR) Model (RFC 1035 §3.2).
 */
class ResourceRecord
{
    private string $name;
    private int $type;
    private int $class;
    private int $ttl;
    private mixed $data;
    private ?string $id;

    public function __construct(
        string $name,
        int|string $type,
        mixed $data,
        int $ttl = 300,
        int $class = RecordType::CLASS_IN,
        ?string $id = null
    ) {
        $this->name = strtolower(trim($name, '.'));
        $this->type = is_int($type) ? $type : RecordType::nameToType($type);
        $this->data = $data;
        $this->ttl = max(0, $ttl);
        $this->class = $class;
        $this->id = $id;
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

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function setTtl(int $ttl): self
    {
        $this->ttl = max(0, $ttl);
        return $this;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public static function a(string $name, string $ip, int $ttl = 300): self
    {
        return new self($name, RecordType::A, $ip, $ttl);
    }

    public static function aaaa(string $name, string $ipv6, int $ttl = 300): self
    {
        return new self($name, RecordType::AAAA, $ipv6, $ttl);
    }

    public static function cname(string $name, string $target, int $ttl = 300): self
    {
        return new self($name, RecordType::CNAME, $target, $ttl);
    }

    public static function mx(string $name, int $preference, string $exchange, int $ttl = 300): self
    {
        return new self($name, RecordType::MX, ['preference' => $preference, 'exchange' => $exchange], $ttl);
    }

    public static function txt(string $name, string|array $text, int $ttl = 300): self
    {
        return new self($name, RecordType::TXT, $text, $ttl);
    }

    public static function ns(string $name, string $target, int $ttl = 300): self
    {
        return new self($name, RecordType::NS, $target, $ttl);
    }

    public static function soa(
        string $name,
        string $mname,
        string $rname,
        int $serial,
        int $refresh = 3600,
        int $retry = 1800,
        int $expire = 604800,
        int $minimum = 86400,
        int $ttl = 3600
    ): self {
        return new self($name, RecordType::SOA, [
            'mname' => $mname,
            'rname' => $rname,
            'serial' => $serial,
            'refresh' => $refresh,
            'retry' => $retry,
            'expire' => $expire,
            'minimum' => $minimum,
        ], $ttl);
    }

    public static function caa(string $name, int $flags, string $tag, string $value, int $ttl = 300): self
    {
        return new self($name, RecordType::CAA, ['flags' => $flags, 'tag' => $tag, 'value' => $value], $ttl);
    }

    public static function ptr(string $name, string $target, int $ttl = 300): self
    {
        return new self($name, RecordType::PTR, $target, $ttl);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->getTypeName(),
            'class' => $this->class,
            'ttl' => $this->ttl,
            'data' => $this->data,
        ];
    }
}
