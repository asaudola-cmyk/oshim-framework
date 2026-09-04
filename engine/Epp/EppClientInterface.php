<?php
declare(strict_types=1);

namespace Oshim\Epp;

use Oshim\Epp\Model\EppResponse;
use Oshim\Epp\Model\GreetingInfo;
use Oshim\Epp\Model\DomainCheckResult;
use Oshim\Epp\Model\DomainInfoResult;
use Oshim\Epp\Model\PollResult;
use Oshim\Epp\Transport\EppTransportInterface;

/**
 * Public contract for EPP registry client operations (RFC 5730-5734).
 */
interface EppClientInterface
{
    /**
     * Connects to the registry and processes the initial greeting.
     */
    public function connect(): GreetingInfo;

    /**
     * Returns the cached server greeting if connected.
     */
    public function getGreeting(): ?GreetingInfo;

    /**
     * Authenticates with registrar credentials.
     */
    public function login(string $clID, string $pw, ?string $newPw = null): EppResponse;

    /**
     * Terminates the session and disconnects.
     */
    public function logout(): EppResponse;

    /**
     * Polls the registry message queue.
     */
    public function poll(string $op = 'req', ?string $msgId = null): PollResult;

    /**
     * Checks availability for one or more domain names.
     *
     * @param list<string> $domains
     * @return array<string, DomainCheckResult>
     */
    public function checkDomains(array $domains): array;

    /**
     * Retrieves full registration details for a domain.
     */
    public function getDomainInfo(string $domain, ?string $authPw = null, string $hosts = 'all'): DomainInfoResult;

    /**
     * Registers a new domain name.
     *
     * @param list<string> $nameservers
     * @param array<string, string|list<string>> $contacts
     */
    public function createDomain(
        string $domain,
        int $years,
        array $nameservers,
        string $registrant,
        string $authPw,
        array $contacts = []
    ): EppResponse;

    /**
     * Extends domain registration validity.
     */
    public function renewDomain(string $domain, string $curExpDate, int $years = 1): EppResponse;

    /**
     * Initiates or manages domain transfer.
     */
    public function transferDomain(string $domain, string $authPw, string $op = 'request', ?int $years = null): EppResponse;

    /**
     * Modifies domain nameservers, contacts, or status.
     *
     * @param array<string, mixed> $add
     * @param array<string, mixed> $rem
     * @param array<string, mixed> $chg
     */
    public function updateDomain(string $domain, array $add = [], array $rem = [], array $chg = []): EppResponse;

    /**
     * Convenience method to add/remove nameservers on a domain.
     *
     * @param list<string> $addNs
     * @param list<string> $remNs
     */
    public function updateNameservers(string $domain, array $addNs, array $remNs = []): EppResponse;

    /**
     * Requests deletion of a domain name.
     */
    public function deleteDomain(string $domain): EppResponse;

    /**
     * Checks availability of nameserver hosts.
     *
     * @param list<string> $hosts
     * @return array<string, bool>
     */
    public function checkHosts(array $hosts): array;

    /**
     * Retrieves details of a nameserver host.
     */
    public function getHostInfo(string $host): EppResponse;

    /**
     * Registers a new host object with optional IPv4 and IPv6 glue records.
     *
     * @param list<string> $ipv4
     * @param list<string> $ipv6
     */
    public function createHost(string $hostName, array $ipv4 = [], array $ipv6 = []): EppResponse;

    /**
     * Modifies host glue records or renames host.
     *
     * @param list<string> $addIpv4
     * @param list<string> $remIpv4
     * @param list<string> $addIpv6
     * @param list<string> $remIpv6
     */
    public function updateHost(
        string $hostName,
        array $addIpv4 = [],
        array $remIpv4 = [],
        array $addIpv6 = [],
        array $remIpv6 = [],
        ?string $newHostName = null
    ): EppResponse;

    /**
     * Deletes a host object.
     */
    public function deleteHost(string $hostName): EppResponse;

    /**
     * Checks availability of contact IDs.
     *
     * @param list<string> $contacts
     * @return array<string, bool>
     */
    public function checkContacts(array $contacts): array;

    /**
     * Retrieves contact profile details.
     */
    public function getContactInfo(string $contactId, ?string $authPw = null): EppResponse;

    /**
     * Creates a new contact object.
     *
     * @param array<string, mixed> $postalInfo
     */
    public function createContact(
        string $id,
        array $postalInfo,
        string $email,
        ?string $voice = null,
        ?string $fax = null,
        ?string $authPw = null
    ): EppResponse;

    /**
     * Deletes a contact object.
     */
    public function deleteContact(string $contactId): EppResponse;

    /**
     * Sends arbitrary EPP XML command string and parses response.
     */
    public function sendXml(string $xml, bool $throwOnError = true): EppResponse;

    /**
     * Returns the underlying transport instance.
     */
    public function getTransport(): EppTransportInterface;
}
