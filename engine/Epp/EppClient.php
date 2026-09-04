<?php
declare(strict_types=1);

namespace Oshim\Epp;

use Oshim\Epp\Model\EppResponse;
use Oshim\Epp\Model\GreetingInfo;
use Oshim\Epp\Model\DomainCheckResult;
use Oshim\Epp\Model\DomainInfoResult;
use Oshim\Epp\Model\PollResult;
use Oshim\Epp\Transport\EppTransportInterface;
use Oshim\Epp\Transport\TlsStreamTransport;
use Oshim\Epp\Xml\EppXmlBuilder;
use Oshim\Epp\Xml\EppXmlParser;

/**
 * Concrete High-Level EPP Client Orchestrator.
 */
class EppClient implements EppClientInterface
{
    private EppTransportInterface $transport;
    private ?GreetingInfo $greeting = null;
    private bool $authenticated = false;

    public function __construct(?EppTransportInterface $transport = null)
    {
        $this->transport = $transport ?? new TlsStreamTransport();
    }

    public function connect(): GreetingInfo
    {
        $greetingXml = $this->transport->connect();
        $this->greeting = EppXmlParser::parseGreeting($greetingXml);
        return $this->greeting;
    }

    public function getGreeting(): ?GreetingInfo
    {
        return $this->greeting;
    }

    public function login(string $clID, string $pw, ?string $newPw = null): EppResponse
    {
        if (!$this->transport->isConnected()) {
            $this->connect();
        }

        $xml = EppXmlBuilder::buildLogin($clID, $pw, $newPw);
        $resp = $this->sendXml($xml, true);

        if ($resp->isSuccess()) {
            $this->authenticated = true;
        }

        return $resp;
    }

    public function logout(): EppResponse
    {
        $xml = EppXmlBuilder::buildLogout();
        try {
            $resp = $this->sendXml($xml, false);
        } finally {
            $this->authenticated = false;
            $this->transport->disconnect();
        }
        return $resp;
    }

    public function poll(string $op = 'req', ?string $msgId = null): PollResult
    {
        $xml = EppXmlBuilder::buildPoll($op, $msgId);
        $rawXml = $this->transport->sendAndReceive($xml);
        return EppXmlParser::parsePoll($rawXml);
    }

    public function checkDomains(array $domains): array
    {
        $xml = EppXmlBuilder::buildDomainCheck($domains);
        $rawXml = $this->transport->sendAndReceive($xml);
        EppXmlParser::parseResponse($rawXml, true);
        return EppXmlParser::parseDomainCheck($rawXml);
    }

    public function getDomainInfo(string $domain, ?string $authPw = null, string $hosts = 'all'): DomainInfoResult
    {
        $xml = EppXmlBuilder::buildDomainInfo($domain, $authPw, $hosts);
        $rawXml = $this->transport->sendAndReceive($xml);
        EppXmlParser::parseResponse($rawXml, true);
        return EppXmlParser::parseDomainInfo($rawXml);
    }

    public function createDomain(
        string $domain,
        int $years,
        array $nameservers,
        string $registrant,
        string $authPw,
        array $contacts = []
    ): EppResponse {
        $xml = EppXmlBuilder::buildDomainCreate($domain, $years, $nameservers, $registrant, $authPw, $contacts);
        return $this->sendXml($xml, true);
    }

    public function renewDomain(string $domain, string $curExpDate, int $years = 1): EppResponse
    {
        $xml = EppXmlBuilder::buildDomainRenew($domain, $curExpDate, $years);
        return $this->sendXml($xml, true);
    }

    public function transferDomain(string $domain, string $authPw, string $op = 'request', ?int $years = null): EppResponse
    {
        $xml = EppXmlBuilder::buildDomainTransfer($domain, $authPw, $op, $years);
        return $this->sendXml($xml, true);
    }

    public function updateDomain(string $domain, array $add = [], array $rem = [], array $chg = []): EppResponse
    {
        $xml = EppXmlBuilder::buildDomainUpdate($domain, $add, $rem, $chg);
        return $this->sendXml($xml, true);
    }

    public function updateNameservers(string $domain, array $addNs, array $remNs = []): EppResponse
    {
        return $this->updateDomain($domain, ['ns' => $addNs], ['ns' => $remNs]);
    }

    public function deleteDomain(string $domain): EppResponse
    {
        $xml = EppXmlBuilder::buildDomainDelete($domain);
        return $this->sendXml($xml, true);
    }

    public function checkHosts(array $hosts): array
    {
        $xml = EppXmlBuilder::buildHostCheck($hosts);
        $rawXml = $this->transport->sendAndReceive($xml);
        EppXmlParser::parseResponse($rawXml, true);
        return EppXmlParser::parseHostCheck($rawXml);
    }

    public function getHostInfo(string $host): EppResponse
    {
        $xml = EppXmlBuilder::buildHostInfo($host);
        return $this->sendXml($xml, true);
    }

    public function createHost(string $hostName, array $ipv4 = [], array $ipv6 = []): EppResponse
    {
        $xml = EppXmlBuilder::buildHostCreate($hostName, $ipv4, $ipv6);
        return $this->sendXml($xml, true);
    }

    public function updateHost(
        string $hostName,
        array $addIpv4 = [],
        array $remIpv4 = [],
        array $addIpv6 = [],
        array $remIpv6 = [],
        ?string $newHostName = null
    ): EppResponse {
        $xml = EppXmlBuilder::buildHostUpdate($hostName, $addIpv4, $remIpv4, $addIpv6, $remIpv6, $newHostName);
        return $this->sendXml($xml, true);
    }

    public function deleteHost(string $hostName): EppResponse
    {
        $xml = EppXmlBuilder::buildHostDelete($hostName);
        return $this->sendXml($xml, true);
    }

    public function checkContacts(array $contacts): array
    {
        $xml = EppXmlBuilder::buildContactCheck($contacts);
        $rawXml = $this->transport->sendAndReceive($xml);
        EppXmlParser::parseResponse($rawXml, true);
        return EppXmlParser::parseContactCheck($rawXml);
    }

    public function getContactInfo(string $contactId, ?string $authPw = null): EppResponse
    {
        $xml = EppXmlBuilder::buildContactInfo($contactId, $authPw);
        return $this->sendXml($xml, true);
    }

    public function createContact(
        string $id,
        array $postalInfo,
        string $email,
        ?string $voice = null,
        ?string $fax = null,
        ?string $authPw = null
    ): EppResponse {
        $xml = EppXmlBuilder::buildContactCreate($id, $postalInfo, $email, $voice, $fax, $authPw);
        return $this->sendXml($xml, true);
    }

    public function deleteContact(string $contactId): EppResponse
    {
        $xml = EppXmlBuilder::buildContactDelete($contactId);
        return $this->sendXml($xml, true);
    }

    public function sendXml(string $xml, bool $throwOnError = true): EppResponse
    {
        $responseXml = $this->transport->sendAndReceive($xml);
        return EppXmlParser::parseResponse($responseXml, $throwOnError);
    }

    public function getTransport(): EppTransportInterface
    {
        return $this->transport;
    }
}
