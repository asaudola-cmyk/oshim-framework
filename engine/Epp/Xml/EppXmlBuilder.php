<?php
declare(strict_types=1);

namespace Oshim\Epp\Xml;

use InvalidArgumentException;

/**
 * Pure PHP RFC 5730-5733 EPP XML Command Envelope and Payload Builder.
 */
class EppXmlBuilder
{
    public const NS_EPP = 'urn:ietf:params:xml:ns:epp-1.0';
    public const NS_DOMAIN = 'urn:ietf:params:xml:ns:domain-1.0';
    public const NS_HOST = 'urn:ietf:params:xml:ns:host-1.0';
    public const NS_CONTACT = 'urn:ietf:params:xml:ns:contact-1.0';
    public const NS_SECDNS = 'urn:ietf:params:xml:ns:secDNS-1.1';

    /**
     * Safely escapes XML text and attribute values for UTF-8 XML 1.0 envelopes.
     */
    private static function escape(string $val): string
    {
        return htmlspecialchars($val, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Normalizes and generates <domain:contact> elements supporting both string and array formats.
     */
    private static function buildDomainContactXml(array $contacts, string $indent = '        '): string
    {
        $xml = '';
        foreach ($contacts as $type => $contactItem) {
            if (is_array($contactItem)) {
                // Assoc array format: ['type' => 'admin', 'id' => 'c1']
                if (isset($contactItem['type']) && (isset($contactItem['id']) || isset($contactItem['contact']))) {
                    $cType = (string)$contactItem['type'];
                    $cId = (string)($contactItem['id'] ?? $contactItem['contact']);
                    $xml .= "\n{$indent}<domain:contact type=\"" . self::escape($cType) . "\">" . self::escape($cId) . "</domain:contact>";
                } else {
                    // Array of contact IDs: ['tech' => ['TEC-1', 'TEC-2']] or list of maps
                    foreach ($contactItem as $subKey => $subVal) {
                        if (is_array($subVal)) {
                            if (isset($subVal['type']) && (isset($subVal['id']) || isset($subVal['contact']))) {
                                $cType = (string)$subVal['type'];
                                $cId = (string)($subVal['id'] ?? $subVal['contact']);
                                $xml .= "\n{$indent}<domain:contact type=\"" . self::escape($cType) . "\">" . self::escape($cId) . "</domain:contact>";
                            }
                        } else {
                            $cType = is_string($type) ? $type : 'admin';
                            $xml .= "\n{$indent}<domain:contact type=\"" . self::escape($cType) . "\">" . self::escape((string)$subVal) . "</domain:contact>";
                        }
                    }
                }
            } else {
                $xml .= "\n{$indent}<domain:contact type=\"" . self::escape((string)$type) . "\">" . self::escape((string)$contactItem) . "</domain:contact>";
            }
        }
        return $xml;
    }

    public static function generateClTRID(string $prefix = 'OSHIM'): string
    {
        return sprintf('%s-%s-%s', $prefix, date('YmdHis'), bin2hex(random_bytes(3)));
    }

    public static function buildHello(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <hello/>
</epp>
XML;
    }

    public static function buildLogin(
        string $clID,
        string $pw,
        ?string $newPw = null,
        array $options = ['version' => '1.0', 'lang' => 'en'],
        array $objUris = [],
        array $extUris = [],
        string $clTRID = ''
    ): string {
        $clTRID = $clTRID ?: self::generateClTRID('LOGIN');
        $version = self::escape($options['version'] ?? '1.0');
        $lang = self::escape($options['lang'] ?? 'en');
        $clIDSafe = self::escape($clID);
        $pwSafe = self::escape($pw);

        $newPwXml = $newPw !== null ? "\n        <newPW>" . self::escape($newPw) . "</newPW>" : '';

        if (empty($objUris)) {
            $objUris = [self::NS_DOMAIN, self::NS_HOST, self::NS_CONTACT];
        }

        $objUriXml = '';
        foreach ($objUris as $uri) {
            $objUriXml .= "\n        <objURI>" . self::escape($uri) . "</objURI>";
        }

        $extUriXml = '';
        if (!empty($extUris)) {
            $extUriXml = "\n        <svcExtension>";
            foreach ($extUris as $uri) {
                $extUriXml .= "\n          <extURI>" . self::escape($uri) . "</extURI>";
            }
            $extUriXml .= "\n        </svcExtension>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <login>
      <clID>{$clIDSafe}</clID>
      <pw>{$pwSafe}</pw>{$newPwXml}
      <options>
        <version>{$version}</version>
        <lang>{$lang}</lang>
      </options>
      <svcs>{$objUriXml}{$extUriXml}
      </svcs>
    </login>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildLogout(string $clTRID = ''): string
    {
        $clTRID = $clTRID ?: self::generateClTRID('LOGOUT');
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <logout/>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildPoll(string $op = 'req', ?string $msgId = null, string $clTRID = ''): string
    {
        $clTRID = $clTRID ?: self::generateClTRID('POLL');
        $opSafe = self::escape($op);
        $msgAttr = $msgId !== null ? ' msgID="' . self::escape($msgId) . '"' : '';

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <poll op="{$opSafe}"{$msgAttr}/>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildDomainCheck(array $domains, string $clTRID = ''): string
    {
        $clTRID = $clTRID ?: self::generateClTRID('CHK-DOM');
        $namesXml = '';
        foreach ($domains as $d) {
            $d = strtolower(trim((string)$d));
            if ($d !== '') {
                $namesXml .= "\n        <domain:name>" . self::escape($d) . "</domain:name>";
            }
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <check>
      <domain:check xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">{$namesXml}
      </domain:check>
    </check>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildDomainInfo(
        string $domain,
        ?string $authPw = null,
        string $hosts = 'all',
        string $clTRID = ''
    ): string {
        $clTRID = $clTRID ?: self::generateClTRID('INF-DOM');
        $domainSafe = self::escape(strtolower(trim($domain)));
        $hostsAttr = $hosts !== '' ? ' hosts="' . self::escape($hosts) . '"' : '';

        $authXml = '';
        if ($authPw !== null) {
            $authXml = "\n        <domain:authInfo>\n          <domain:pw>" . self::escape($authPw) . "</domain:pw>\n        </domain:authInfo>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <info>
      <domain:info xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name{$hostsAttr}>{$domainSafe}</domain:name>{$authXml}
      </domain:info>
    </info>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildDomainCreate(
        string $domain,
        int $years,
        array $nameservers,
        string $registrant,
        string $authPw,
        array $contacts = [],
        string $clTRID = ''
    ): string {
        $clTRID = $clTRID ?: self::generateClTRID('CRE-DOM');
        $domainSafe = self::escape(strtolower(trim($domain)));
        $years = max(1, $years);

        $nsXml = '';
        if (!empty($nameservers)) {
            $nsXml = "\n        <domain:ns>";
            foreach ($nameservers as $ns) {
                $nsClean = trim((string)$ns);
                if ($nsClean !== '') {
                    $nsXml .= "\n          <domain:hostObj>" . self::escape($nsClean) . "</domain:hostObj>";
                }
            }
            $nsXml .= "\n        </domain:ns>";
        }

        $regXml = $registrant !== '' ? "\n        <domain:registrant>" . self::escape($registrant) . "</domain:registrant>" : '';

        $contactXml = self::buildDomainContactXml($contacts, '        ');

        $authXml = "\n        <domain:authInfo>\n          <domain:pw>" . self::escape($authPw) . "</domain:pw>\n        </domain:authInfo>";

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <create>
      <domain:create xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name>{$domainSafe}</domain:name>
        <domain:period unit="y">{$years}</domain:period>{$nsXml}{$regXml}{$contactXml}{$authXml}
      </domain:create>
    </create>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildDomainDelete(string $domain, string $clTRID = ''): string
    {
        $clTRID = $clTRID ?: self::generateClTRID('DEL-DOM');
        $domainSafe = self::escape(strtolower(trim($domain)));

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <delete>
      <domain:delete xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name>{$domainSafe}</domain:name>
      </domain:delete>
    </delete>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildDomainRenew(
        string $domain,
        string $curExpDate,
        int $years = 1,
        string $clTRID = ''
    ): string {
        $clTRID = $clTRID ?: self::generateClTRID('REN-DOM');
        $domainSafe = self::escape(strtolower(trim($domain)));
        $curExpDateSafe = self::escape(trim($curExpDate));
        $years = max(1, $years);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <renew>
      <domain:renew xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name>{$domainSafe}</domain:name>
        <domain:curExpDate>{$curExpDateSafe}</domain:curExpDate>
        <domain:period unit="y">{$years}</domain:period>
      </domain:renew>
    </renew>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildDomainTransfer(
        string $domain,
        string $authPw,
        string $op = 'request',
        ?int $years = null,
        string $clTRID = ''
    ): string {
        $clTRID = $clTRID ?: self::generateClTRID('TRN-DOM');
        $domainSafe = self::escape(strtolower(trim($domain)));
        $opSafe = self::escape($op);

        $periodXml = $years !== null ? "\n        <domain:period unit=\"y\">" . max(1, $years) . "</domain:period>" : '';
        $authXml = $authPw !== '' ? "\n        <domain:authInfo>\n          <domain:pw>" . self::escape($authPw) . "</domain:pw>\n        </domain:authInfo>" : '';

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <transfer op="{$opSafe}">
      <domain:transfer xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name>{$domainSafe}</domain:name>{$periodXml}{$authXml}
      </domain:transfer>
    </transfer>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildDomainUpdate(
        string $domain,
        array $add = [],
        array $rem = [],
        array $chg = [],
        string $clTRID = ''
    ): string {
        $clTRID = $clTRID ?: self::generateClTRID('UPD-DOM');
        $domainSafe = self::escape(strtolower(trim($domain)));

        $addXml = '';
        if (!empty($add['ns']) || !empty($add['contact']) || !empty($add['status'])) {
            $addXml = "\n        <domain:add>";
            if (!empty($add['ns'])) {
                $addXml .= "\n          <domain:ns>";
                foreach ((array)$add['ns'] as $ns) {
                    $addXml .= "\n            <domain:hostObj>" . self::escape(trim((string)$ns)) . "</domain:hostObj>";
                }
                $addXml .= "\n          </domain:ns>";
            }
            if (!empty($add['contact'])) {
                $addXml .= self::buildDomainContactXml((array)$add['contact'], '          ');
            }
            if (!empty($add['status'])) {
                foreach ((array)$add['status'] as $st) {
                    $addXml .= "\n          <domain:status s=\"" . self::escape((string)$st) . "\"/>";
                }
            }
            $addXml .= "\n        </domain:add>";
        }

        $remXml = '';
        if (!empty($rem['ns']) || !empty($rem['contact']) || !empty($rem['status'])) {
            $remXml = "\n        <domain:rem>";
            if (!empty($rem['ns'])) {
                $remXml .= "\n          <domain:ns>";
                foreach ((array)$rem['ns'] as $ns) {
                    $remXml .= "\n            <domain:hostObj>" . self::escape(trim((string)$ns)) . "</domain:hostObj>";
                }
                $remXml .= "\n          </domain:ns>";
            }
            if (!empty($rem['contact'])) {
                $remXml .= self::buildDomainContactXml((array)$rem['contact'], '          ');
            }
            if (!empty($rem['status'])) {
                foreach ((array)$rem['status'] as $st) {
                    $remXml .= "\n          <domain:status s=\"" . self::escape((string)$st) . "\"/>";
                }
            }
            $remXml .= "\n        </domain:rem>";
        }

        $chgXml = '';
        if (!empty($chg['registrant']) || !empty($chg['authInfo'])) {
            $chgXml = "\n        <domain:chg>";
            if (!empty($chg['registrant'])) {
                $chgXml .= "\n          <domain:registrant>" . self::escape((string)$chg['registrant']) . "</domain:registrant>";
            }
            if (!empty($chg['authInfo'])) {
                $chgXml .= "\n          <domain:authInfo>\n            <domain:pw>" . self::escape((string)$chg['authInfo']) . "</domain:pw>\n          </domain:authInfo>";
            }
            $chgXml .= "\n        </domain:chg>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <update>
      <domain:update xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name>{$domainSafe}</domain:name>{$addXml}{$remXml}{$chgXml}
      </domain:update>
    </update>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildHostCheck(array $hosts, string $clTRID = ''): string
    {
        $clTRID = $clTRID ?: self::generateClTRID('CHK-HST');
        $namesXml = '';
        foreach ($hosts as $h) {
            $h = strtolower(trim((string)$h));
            if ($h !== '') {
                $namesXml .= "\n        <host:name>" . self::escape($h) . "</host:name>";
            }
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <check>
      <host:check xmlns:host="urn:ietf:params:xml:ns:host-1.0">{$namesXml}
      </host:check>
    </check>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildHostInfo(string $host, string $clTRID = ''): string
    {
        $clTRID = $clTRID ?: self::generateClTRID('INF-HST');
        $hostSafe = self::escape(strtolower(trim($host)));

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <info>
      <host:info xmlns:host="urn:ietf:params:xml:ns:host-1.0">
        <host:name>{$hostSafe}</host:name>
      </host:info>
    </info>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildHostCreate(
        string $hostName,
        array $ipv4 = [],
        array $ipv6 = [],
        string $clTRID = ''
    ): string {
        $clTRID = $clTRID ?: self::generateClTRID('CRE-HST');
        $hostSafe = self::escape(strtolower(trim($hostName)));

        $addrsXml = '';
        foreach ($ipv4 as $ip) {
            $ipClean = trim((string)$ip);
            if ($ipClean !== '') {
                if (!filter_var($ipClean, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    throw new InvalidArgumentException("Invalid IPv4 glue address: {$ipClean}");
                }
                $addrsXml .= "\n        <host:addr ip=\"v4\">" . self::escape($ipClean) . "</host:addr>";
            }
        }

        foreach ($ipv6 as $ip) {
            $ipClean = trim((string)$ip);
            if ($ipClean !== '') {
                if (!filter_var($ipClean, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    throw new InvalidArgumentException("Invalid IPv6 glue address: {$ipClean}");
                }
                $addrsXml .= "\n        <host:addr ip=\"v6\">" . self::escape($ipClean) . "</host:addr>";
            }
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <create>
      <host:create xmlns:host="urn:ietf:params:xml:ns:host-1.0">
        <host:name>{$hostSafe}</host:name>{$addrsXml}
      </host:create>
    </create>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildHostDelete(string $hostName, string $clTRID = ''): string
    {
        $clTRID = $clTRID ?: self::generateClTRID('DEL-HST');
        $hostSafe = self::escape(strtolower(trim($hostName)));

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <delete>
      <host:delete xmlns:host="urn:ietf:params:xml:ns:host-1.0">
        <host:name>{$hostSafe}</host:name>
      </host:delete>
    </delete>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildHostUpdate(
        string $hostName,
        array $addIpv4 = [],
        array $remIpv4 = [],
        array $addIpv6 = [],
        array $remIpv6 = [],
        ?string $newHostName = null,
        string $clTRID = ''
    ): string {
        $clTRID = $clTRID ?: self::generateClTRID('UPD-HST');
        $hostSafe = self::escape(strtolower(trim($hostName)));

        $addXml = '';
        if (!empty($addIpv4) || !empty($addIpv6)) {
            $addXml = "\n        <host:add>";
            foreach ($addIpv4 as $ip) {
                $ipClean = trim((string)$ip);
                if ($ipClean !== '') {
                    if (!filter_var($ipClean, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        throw new InvalidArgumentException("Invalid IPv4 glue address: {$ipClean}");
                    }
                    $addXml .= "\n          <host:addr ip=\"v4\">" . self::escape($ipClean) . "</host:addr>";
                }
            }
            foreach ($addIpv6 as $ip) {
                $ipClean = trim((string)$ip);
                if ($ipClean !== '') {
                    if (!filter_var($ipClean, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        throw new InvalidArgumentException("Invalid IPv6 glue address: {$ipClean}");
                    }
                    $addXml .= "\n          <host:addr ip=\"v6\">" . self::escape($ipClean) . "</host:addr>";
                }
            }
            $addXml .= "\n        </host:add>";
        }

        $remXml = '';
        if (!empty($remIpv4) || !empty($remIpv6)) {
            $remXml = "\n        <host:rem>";
            foreach ($remIpv4 as $ip) {
                $ipClean = trim((string)$ip);
                if ($ipClean !== '') {
                    if (!filter_var($ipClean, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        throw new InvalidArgumentException("Invalid IPv4 glue address: {$ipClean}");
                    }
                    $remXml .= "\n          <host:addr ip=\"v4\">" . self::escape($ipClean) . "</host:addr>";
                }
            }
            foreach ($remIpv6 as $ip) {
                $ipClean = trim((string)$ip);
                if ($ipClean !== '') {
                    if (!filter_var($ipClean, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        throw new InvalidArgumentException("Invalid IPv6 glue address: {$ipClean}");
                    }
                    $remXml .= "\n          <host:addr ip=\"v6\">" . self::escape($ipClean) . "</host:addr>";
                }
            }
            $remXml .= "\n        </host:rem>";
        }

        $chgXml = '';
        if ($newHostName !== null && trim($newHostName) !== '') {
            $chgXml = "\n        <host:chg>\n          <host:name>" . self::escape(strtolower(trim($newHostName))) . "</host:name>\n        </host:chg>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <update>
      <host:update xmlns:host="urn:ietf:params:xml:ns:host-1.0">
        <host:name>{$hostSafe}</host:name>{$addXml}{$remXml}{$chgXml}
      </host:update>
    </update>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildContactCheck(array $contacts, string $clTRID = ''): string
    {
        $clTRID = $clTRID ?: self::generateClTRID('CHK-CNT');
        $idsXml = '';
        foreach ($contacts as $c) {
            $c = trim((string)$c);
            if ($c !== '') {
                $idsXml .= "\n        <contact:id>" . self::escape($c) . "</contact:id>";
            }
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <check>
      <contact:check xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">{$idsXml}
      </contact:check>
    </check>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildContactInfo(string $contactId, ?string $authPw = null, string $clTRID = ''): string
    {
        $clTRID = $clTRID ?: self::generateClTRID('INF-CNT');
        $idSafe = self::escape(trim($contactId));

        $authXml = '';
        if ($authPw !== null) {
            $authXml = "\n        <contact:authInfo>\n          <contact:pw>" . self::escape($authPw) . "</contact:pw>\n        </contact:authInfo>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <info>
      <contact:info xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">
        <contact:id>{$idSafe}</contact:id>{$authXml}
      </contact:info>
    </info>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildContactCreate(
        string $id,
        array $postalInfo,
        string $email,
        ?string $voice = null,
        ?string $fax = null,
        ?string $authPw = null,
        string $clTRID = ''
    ): string {
        $clTRID = $clTRID ?: self::generateClTRID('CRE-CNT');
        $idSafe = self::escape(trim($id));
        $emailSafe = self::escape(trim($email));

        $type = self::escape($postalInfo['type'] ?? 'int');
        $name = self::escape($postalInfo['name'] ?? '');
        $orgXml = !empty($postalInfo['org']) ? "\n          <contact:org>" . self::escape($postalInfo['org']) . "</contact:org>" : '';

        $streetXml = '';
        if (!empty($postalInfo['street'])) {
            foreach ((array)$postalInfo['street'] as $st) {
                $streetXml .= "\n            <contact:street>" . self::escape((string)$st) . "</contact:street>";
            }
        }

        $city = self::escape($postalInfo['city'] ?? '');
        $spXml = !empty($postalInfo['sp']) ? "\n            <contact:sp>" . self::escape($postalInfo['sp']) . "</contact:sp>" : '';
        $pcXml = !empty($postalInfo['pc']) ? "\n            <contact:pc>" . self::escape($postalInfo['pc']) . "</contact:pc>" : '';
        $cc = self::escape(strtoupper($postalInfo['cc'] ?? 'US'));

        $voiceXml = ($voice !== null && trim($voice) !== '') ? "\n        <contact:voice>" . self::escape(trim($voice)) . "</contact:voice>" : '';
        $faxXml = ($fax !== null && trim($fax) !== '') ? "\n        <contact:fax>" . self::escape(trim($fax)) . "</contact:fax>" : '';
        $authPw = $authPw ?: bin2hex(random_bytes(6));
        $authXml = "\n        <contact:authInfo>\n          <contact:pw>" . self::escape($authPw) . "</contact:pw>\n        </contact:authInfo>";

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <create>
      <contact:create xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">
        <contact:id>{$idSafe}</contact:id>
        <contact:postalInfo type="{$type}">
          <contact:name>{$name}</contact:name>{$orgXml}
          <contact:addr>{$streetXml}
            <contact:city>{$city}</contact:city>{$spXml}{$pcXml}
            <contact:cc>{$cc}</contact:cc>
          </contact:addr>
        </contact:postalInfo>{$voiceXml}{$faxXml}
        <contact:email>{$emailSafe}</contact:email>{$authXml}
      </contact:create>
    </create>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }

    public static function buildContactDelete(string $contactId, string $clTRID = ''): string
    {
        $clTRID = $clTRID ?: self::generateClTRID('DEL-CNT');
        $idSafe = self::escape(trim($contactId));

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <command>
    <delete>
      <contact:delete xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">
        <contact:id>{$idSafe}</contact:id>
      </contact:delete>
    </delete>
    <clTRID>{$clTRID}</clTRID>
  </command>
</epp>
XML;
    }
}
