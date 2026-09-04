<?php
declare(strict_types=1);

namespace Oshim\Epp\Transport;

use Oshim\Epp\Exceptions\EppTransportException;

/**
 * In-Memory Transport for Unit Testing and Offline Simulation.
 */
class MemoryTransport implements EppTransportInterface
{
    private bool $connected = false;
    private ?string $greetingXml = null;
    /** @var (callable(string): string)|null */
    private $requestHandler = null;
    /** @var list<string> */
    private array $history = [];
    /** @var list<string> */
    private array $responseQueue = [];

    public function __construct(?string $greetingXml = null, ?callable $requestHandler = null)
    {
        $this->greetingXml = $greetingXml;
        $this->requestHandler = $requestHandler;
    }

    public function setRequestHandler(callable $handler): void
    {
        $this->requestHandler = $handler;
    }

    public function queueResponse(string $xml): void
    {
        $this->responseQueue[] = $xml;
    }

    public function connect(): string
    {
        $this->connected = true;
        if ($this->greetingXml !== null) {
            return $this->greetingXml;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <greeting>
    <svID>OSHIM-MOCK-REGISTRY</svID>
    <svDate>2026-08-29T00:00:00Z</svDate>
    <svcMenu>
      <version>1.0</version>
      <lang>en</lang>
      <objURI>urn:ietf:params:xml:ns:domain-1.0</objURI>
      <objURI>urn:ietf:params:xml:ns:host-1.0</objURI>
      <objURI>urn:ietf:params:xml:ns:contact-1.0</objURI>
    </svcMenu>
    <dcp>
      <access><all/></access>
      <statement>
        <purpose><admin/><prov/></purpose>
        <recipient><ours/></recipient>
        <retention><stated/></retention>
      </statement>
    </dcp>
  </greeting>
</epp>
XML;
    }

    public function send(string $xml): void
    {
        if (!$this->connected) {
            throw new EppTransportException("Memory transport is not connected.");
        }
        $this->history[] = $xml;
    }

    public function receive(): string
    {
        if (!$this->connected) {
            throw new EppTransportException("Memory transport is not connected.");
        }

        if (!empty($this->responseQueue)) {
            return array_shift($this->responseQueue);
        }

        $lastRequest = !empty($this->history) ? end($this->history) : '';
        if ($this->requestHandler !== null) {
            return ($this->requestHandler)($lastRequest);
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <response>
    <result code="1000">
      <msg>Command completed successfully</msg>
    </result>
    <trID>
      <clTRID>CLTRID-1</clTRID>
      <svTRID>SVTRID-1</svTRID>
    </trID>
  </response>
</epp>
XML;
    }

    public function sendAndReceive(string $xml): string
    {
        $this->send($xml);
        return $this->receive();
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * @return list<string>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    public function getLastCommand(): ?string
    {
        return !empty($this->history) ? end($this->history) : null;
    }

    public function clearHistory(): void
    {
        $this->history = [];
    }
}
