<?php
declare(strict_types=1);

namespace Oshim\Epp\Model;

/**
 * Result DTO for Domain Information Query (RFC 5731 §3.1.2).
 */
class DomainInfoResult
{
    private string $name;
    private string $roid;
    /** @var list<string> */
    private array $status;
    private ?string $registrant;
    /** @var array<string, string> */
    private array $contacts;
    /** @var list<string> */
    private array $nameservers;
    /** @var list<string> */
    private array $hosts;
    private ?string $clID;
    private ?string $crID;
    private ?string $crDate;
    private ?string $upDate;
    private ?string $exDate;
    private ?string $trDate;
    private ?string $authPw;

    public function __construct(
        string $name,
        string $roid = '',
        array $status = ['ok'],
        ?string $registrant = null,
        array $contacts = [],
        array $nameservers = [],
        array $hosts = [],
        ?string $clID = null,
        ?string $crID = null,
        ?string $crDate = null,
        ?string $upDate = null,
        ?string $exDate = null,
        ?string $trDate = null,
        ?string $authPw = null
    ) {
        $this->name = $name;
        $this->roid = $roid;
        $this->status = $status;
        $this->registrant = $registrant;
        $this->contacts = $contacts;
        $this->nameservers = $nameservers;
        $this->hosts = $hosts;
        $this->clID = $clID;
        $this->crID = $crID;
        $this->crDate = $crDate;
        $this->upDate = $upDate;
        $this->exDate = $exDate;
        $this->trDate = $trDate;
        $this->authPw = $authPw;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRoid(): string
    {
        return $this->roid;
    }

    /**
     * @return list<string>
     */
    public function getStatus(): array
    {
        return $this->status;
    }

    public function getRegistrant(): ?string
    {
        return $this->registrant;
    }

    /**
     * @return array<string, string>
     */
    public function getContacts(): array
    {
        return $this->contacts;
    }

    /**
     * @return list<string>
     */
    public function getNameservers(): array
    {
        return $this->nameservers;
    }

    /**
     * @return list<string>
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    public function getClID(): ?string
    {
        return $this->clID;
    }

    public function getCrID(): ?string
    {
        return $this->crID;
    }

    public function getCrDate(): ?string
    {
        return $this->crDate;
    }

    public function getUpDate(): ?string
    {
        return $this->upDate;
    }

    public function getExDate(): ?string
    {
        return $this->exDate;
    }

    public function getTrDate(): ?string
    {
        return $this->trDate;
    }

    public function getAuthPw(): ?string
    {
        return $this->authPw;
    }
}
