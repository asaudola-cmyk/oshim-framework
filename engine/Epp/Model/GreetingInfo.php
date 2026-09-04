<?php
declare(strict_types=1);

namespace Oshim\Epp\Model;

/**
 * Registry Greeting Information (RFC 5730 §2.4).
 */
class GreetingInfo
{
    private string $serverIdentifier;
    private string $serverDate;
    /** @var list<string> */
    private array $versions;
    /** @var list<string> */
    private array $languages;
    /** @var list<string> */
    private array $objectUris;
    /** @var list<string> */
    private array $extensionUris;
    /** @var array<string, mixed> */
    private array $dcp;

    public function __construct(
        string $serverIdentifier,
        string $serverDate,
        array $versions = ['1.0'],
        array $languages = ['en'],
        array $objectUris = [],
        array $extensionUris = [],
        array $dcp = []
    ) {
        $this->serverIdentifier = $serverIdentifier;
        $this->serverDate = $serverDate;
        $this->versions = $versions;
        $this->languages = $languages;
        $this->objectUris = $objectUris;
        $this->extensionUris = $extensionUris;
        $this->dcp = $dcp;
    }

    public function getServerIdentifier(): string
    {
        return $this->serverIdentifier;
    }

    public function getServerDate(): string
    {
        return $this->serverDate;
    }

    /**
     * @return list<string>
     */
    public function getVersions(): array
    {
        return $this->versions;
    }

    /**
     * @return list<string>
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * @return list<string>
     */
    public function getObjectUris(): array
    {
        return $this->objectUris;
    }

    /**
     * @return list<string>
     */
    public function getExtensionUris(): array
    {
        return $this->extensionUris;
    }

    public function getDcp(): array
    {
        return $this->dcp;
    }
}
