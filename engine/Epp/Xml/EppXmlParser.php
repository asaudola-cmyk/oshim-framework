<?php
declare(strict_types=1);

namespace Oshim\Epp\Xml;

use DOMDocument;
use DOMXPath;
use DOMElement;
use Oshim\Epp\Model\EppResponse;
use Oshim\Epp\Model\GreetingInfo;
use Oshim\Epp\Model\DomainCheckResult;
use Oshim\Epp\Model\DomainInfoResult;
use Oshim\Epp\Model\PollResult;
use Oshim\Epp\Exceptions\EppXmlException;
use Oshim\Epp\Exceptions\EppResponseException;
use Oshim\Epp\Exceptions\EppAuthException;
use Oshim\Epp\Exceptions\EppObjectExistsException;
use Oshim\Epp\Exceptions\EppObjectNotFoundException;
use Oshim\Epp\Exceptions\EppObjectStatusProhibitsException;
use Oshim\Epp\Exceptions\EppPolicyException;
use Oshim\Epp\Exceptions\EppBillingException;
use Oshim\Epp\Exceptions\EppSessionLimitException;

/**
 * Pure PHP RFC 5730-5733 EPP XML Response Parser.
 */
class EppXmlParser
{
    /**
     * Parses an EPP response XML string into a normalized EppResponse object.
     *
     * @throws EppXmlException If the XML is malformed.
     */
    public static function parseResponse(string $xml, bool $throwOnError = false): EppResponse
    {
        $dom = self::loadDom($xml);
        $xpath = new DOMXPath($dom);

        // Result code and message
        $resultNodes = $xpath->query("//*[local-name()='result']");
        if ($resultNodes === false || $resultNodes->length === 0) {
            throw new EppXmlException('Invalid EPP response: missing <result> element.');
        }

        /** @var DOMElement $resultElem */
        $resultElem = $resultNodes->item(0);
        $code = (int)$resultElem->getAttribute('code');
        $message = 'Command completed successfully';
        $msgNodes = $xpath->query(".//*[local-name()='msg']", $resultElem);
        if ($msgNodes !== false && $msgNodes->length > 0) {
            $message = trim($msgNodes->item(0)->textContent);
        }

        // Transaction IDs
        $clTRID = null;
        $clNodes = $xpath->query("//*[local-name()='clTRID']");
        if ($clNodes !== false && $clNodes->length > 0) {
            $clTRID = trim($clNodes->item(0)->textContent);
        }

        $svTRID = null;
        $svNodes = $xpath->query("//*[local-name()='svTRID']");
        if ($svNodes !== false && $svNodes->length > 0) {
            $svTRID = trim($svNodes->item(0)->textContent);
        }

        // resData XML
        $resDataXml = null;
        $parsedData = [];
        $resDataNodes = $xpath->query("//*[local-name()='resData']");
        if ($resDataNodes !== false && $resDataNodes->length > 0) {
            $resDataElem = $resDataNodes->item(0);
            $resDataXml = $dom->saveXML($resDataElem) ?: null;
            $parsedData = self::extractResData($xpath, $resDataElem);
        }

        $response = new EppResponse($code, $message, $clTRID, $svTRID, $resDataXml, $parsedData, $xml);

        if ($throwOnError && !$response->isSuccess()) {
            self::throwForCode($code, $message, $clTRID, $svTRID);
        }

        return $response;
    }

    /**
     * Parses a server greeting XML document into a GreetingInfo model.
     *
     * @throws EppXmlException If XML is malformed or not a greeting.
     */
    public static function parseGreeting(string $xml): GreetingInfo
    {
        $dom = self::loadDom($xml);
        $xpath = new DOMXPath($dom);

        $svIdNodes = $xpath->query("//*[local-name()='svID']");
        $serverIdentifier = ($svIdNodes !== false && $svIdNodes->length > 0) ? trim($svIdNodes->item(0)->textContent) : 'UNKNOWN';

        $svDateNodes = $xpath->query("//*[local-name()='svDate']");
        $serverDate = ($svDateNodes !== false && $svDateNodes->length > 0) ? trim($svDateNodes->item(0)->textContent) : gmdate('Y-m-d\TH:i:s\Z');

        $versions = [];
        $vNodes = $xpath->query("//*[local-name()='version']");
        if ($vNodes !== false) {
            foreach ($vNodes as $v) {
                $versions[] = trim($v->textContent);
            }
        }

        $languages = [];
        $lNodes = $xpath->query("//*[local-name()='lang']");
        if ($lNodes !== false) {
            foreach ($lNodes as $l) {
                $languages[] = trim($l->textContent);
            }
        }

        $objUris = [];
        $objNodes = $xpath->query("//*[local-name()='objURI']");
        if ($objNodes !== false) {
            foreach ($objNodes as $obj) {
                $objUris[] = trim($obj->textContent);
            }
        }

        $extUris = [];
        $extNodes = $xpath->query("//*[local-name()='extURI']");
        if ($extNodes !== false) {
            foreach ($extNodes as $ext) {
                $extUris[] = trim($ext->textContent);
            }
        }

        return new GreetingInfo(
            $serverIdentifier,
            $serverDate,
            !empty($versions) ? $versions : ['1.0'],
            !empty($languages) ? $languages : ['en'],
            $objUris,
            $extUris
        );
    }

    /**
     * Parses a domain:chkData response into an associative array of domain name => DomainCheckResult.
     *
     * @return array<string, DomainCheckResult>
     */
    public static function parseDomainCheck(string $xml): array
    {
        $dom = self::loadDom($xml);
        $xpath = new DOMXPath($dom);
        $results = [];

        $cdNodes = $xpath->query("//*[local-name()='chkData']/*[local-name()='cd']");
        if ($cdNodes !== false) {
            foreach ($cdNodes as $cd) {
                $nameNodes = $xpath->query(".//*[local-name()='name']", $cd);
                if ($nameNodes !== false && $nameNodes->length > 0) {
                    /** @var DOMElement $nameElem */
                    $nameElem = $nameNodes->item(0);
                    $name = strtolower(trim($nameElem->textContent));
                    $availAttr = $nameElem->getAttribute('avail');
                    $isAvail = ($availAttr === '1' || strtolower($availAttr) === 'true');

                    $reason = null;
                    $reasonNodes = $xpath->query(".//*[local-name()='reason']", $cd);
                    if ($reasonNodes !== false && $reasonNodes->length > 0) {
                        $reason = trim($reasonNodes->item(0)->textContent);
                    }

                    $results[$name] = new DomainCheckResult($name, $isAvail, $reason);
                }
            }
        }

        return $results;
    }

    /**
     * Parses a domain:infData response into a DomainInfoResult model.
     */
    public static function parseDomainInfo(string $xml): DomainInfoResult
    {
        $dom = self::loadDom($xml);
        $xpath = new DOMXPath($dom);

        $name = self::nodeText($xpath, "//*[local-name()='infData']/*[local-name()='name']");
        $roid = self::nodeText($xpath, "//*[local-name()='infData']/*[local-name()='roid']");

        $statuses = [];
        $statusNodes = $xpath->query("//*[local-name()='infData']/*[local-name()='status']");
        if ($statusNodes !== false) {
            foreach ($statusNodes as $st) {
                if ($st instanceof DOMElement) {
                    $statuses[] = $st->getAttribute('s') ?: trim($st->textContent);
                }
            }
        }

        $registrant = self::nodeText($xpath, "//*[local-name()='infData']/*[local-name()='registrant']");

        $contacts = [];
        $contactNodes = $xpath->query("//*[local-name()='infData']/*[local-name()='contact']");
        if ($contactNodes !== false) {
            foreach ($contactNodes as $c) {
                if ($c instanceof DOMElement) {
                    $type = $c->getAttribute('type') ?: 'contact';
                    $contacts[$type] = trim($c->textContent);
                }
            }
        }

        $nameservers = [];
        $nsNodes = $xpath->query("//*[local-name()='infData']/*[local-name()='ns']/*[local-name()='hostObj']");
        if ($nsNodes !== false) {
            foreach ($nsNodes as $ns) {
                $nameservers[] = trim($ns->textContent);
            }
        }

        $hosts = [];
        $hostNodes = $xpath->query("//*[local-name()='infData']/*[local-name()='host']");
        if ($hostNodes !== false) {
            foreach ($hostNodes as $h) {
                $hosts[] = trim($h->textContent);
            }
        }

        $clID = self::nodeText($xpath, "//*[local-name()='infData']/*[local-name()='clID']");
        $crID = self::nodeText($xpath, "//*[local-name()='infData']/*[local-name()='crID']");
        $crDate = self::nodeText($xpath, "//*[local-name()='infData']/*[local-name()='crDate']");
        $upDate = self::nodeText($xpath, "//*[local-name()='infData']/*[local-name()='upDate']");
        $exDate = self::nodeText($xpath, "//*[local-name()='infData']/*[local-name()='exDate']");
        $trDate = self::nodeText($xpath, "//*[local-name()='infData']/*[local-name()='trDate']");
        $authPw = self::nodeText($xpath, "//*[local-name()='infData']/*[local-name()='authInfo']/*[local-name()='pw']");

        return new DomainInfoResult(
            $name,
            $roid,
            !empty($statuses) ? $statuses : ['ok'],
            $registrant ?: null,
            $contacts,
            $nameservers,
            $hosts,
            $clID ?: null,
            $crID ?: null,
            $crDate ?: null,
            $upDate ?: null,
            $exDate ?: null,
            $trDate ?: null,
            $authPw ?: null
        );
    }

    /**
     * Parses a poll response XML into a PollResult model.
     */
    public static function parsePoll(string $xml): PollResult
    {
        $dom = self::loadDom($xml);
        $xpath = new DOMXPath($dom);

        $msgQNodes = $xpath->query("//*[local-name()='msgQ']");
        if ($msgQNodes === false || $msgQNodes->length === 0) {
            return new PollResult(false, 0);
        }

        /** @var DOMElement $msgQ */
        $msgQ = $msgQNodes->item(0);
        $count = (int)$msgQ->getAttribute('count');
        $msgId = $msgQ->getAttribute('id') ?: null;

        $enqueueDate = null;
        $qDateNodes = $xpath->query(".//*[local-name()='qDate']", $msgQ);
        if ($qDateNodes !== false && $qDateNodes->length > 0) {
            $enqueueDate = trim($qDateNodes->item(0)->textContent);
        }

        $msg = null;
        $msgNodes = $xpath->query(".//*[local-name()='msg']", $msgQ);
        if ($msgNodes !== false && $msgNodes->length > 0) {
            $msg = trim($msgNodes->item(0)->textContent);
        }

        $resDataXml = null;
        $resNodes = $xpath->query("//*[local-name()='resData']");
        if ($resNodes !== false && $resNodes->length > 0) {
            $resDataXml = $dom->saveXML($resNodes->item(0)) ?: null;
        }

        return new PollResult(true, $count, $msgId, $enqueueDate, $msg, $resDataXml);
    }

    /**
     * Parses host:chkData XML into associative array [hostname => bool].
     *
     * @return array<string, bool>
     */
    public static function parseHostCheck(string $xml): array
    {
        $dom = self::loadDom($xml);
        $xpath = new DOMXPath($dom);
        $results = [];

        $cdNodes = $xpath->query("//*[local-name()='chkData']/*[local-name()='cd']");
        if ($cdNodes !== false) {
            foreach ($cdNodes as $cd) {
                $nameNodes = $xpath->query(".//*[local-name()='name']", $cd);
                if ($nameNodes !== false && $nameNodes->length > 0) {
                    /** @var DOMElement $nameElem */
                    $nameElem = $nameNodes->item(0);
                    $name = strtolower(trim($nameElem->textContent));
                    $availAttr = $nameElem->getAttribute('avail');
                    $results[$name] = ($availAttr === '1' || strtolower($availAttr) === 'true');
                }
            }
        }

        return $results;
    }

    /**
     * Parses contact:chkData XML into associative array [contactId => bool].
     *
     * @return array<string, bool>
     */
    public static function parseContactCheck(string $xml): array
    {
        $dom = self::loadDom($xml);
        $xpath = new DOMXPath($dom);
        $results = [];

        $cdNodes = $xpath->query("//*[local-name()='chkData']/*[local-name()='cd']");
        if ($cdNodes !== false) {
            foreach ($cdNodes as $cd) {
                $idNodes = $xpath->query(".//*[local-name()='id']", $cd);
                if ($idNodes !== false && $idNodes->length > 0) {
                    /** @var DOMElement $idElem */
                    $idElem = $idNodes->item(0);
                    $id = trim($idElem->textContent);
                    $availAttr = $idElem->getAttribute('avail');
                    $results[$id] = ($availAttr === '1' || strtolower($availAttr) === 'true');
                }
            }
        }

        return $results;
    }

    public static function throwForCode(int $code, string $message, ?string $clTRID = null, ?string $svTRID = null): void
    {
        switch ($code) {
            case 2104:
                throw new EppBillingException($code, $message, $clTRID, $svTRID);
            case 2105:
            case 2106:
            case 2306:
            case 2308:
                throw new EppPolicyException($code, $message, $clTRID, $svTRID);
            case 2200:
            case 2201:
            case 2202:
            case 2501:
                throw new EppAuthException($code, $message, $clTRID, $svTRID);
            case 2302:
                throw new EppObjectExistsException($code, $message, $clTRID, $svTRID);
            case 2303:
                throw new EppObjectNotFoundException($code, $message, $clTRID, $svTRID);
            case 2304:
                throw new EppObjectStatusProhibitsException($code, $message, $clTRID, $svTRID);
            case 2502:
                throw new EppSessionLimitException($code, $message, $clTRID, $svTRID);
            default:
                throw new EppResponseException($code, $message, $clTRID, $svTRID);
        }
    }

    private static function loadDom(string $xml): DOMDocument
    {
        if (trim($xml) === '') {
            throw new EppXmlException('EPP XML payload cannot be empty.');
        }

        $prevErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;

        try {
            $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        } catch (\Throwable $e) {
            libxml_clear_errors();
            libxml_use_internal_errors($prevErrors);
            throw new EppXmlException('Failed to parse EPP XML: ' . $e->getMessage(), 0, $e);
        }

        if (!$loaded) {
            $error = libxml_get_last_error();
            libxml_clear_errors();
            libxml_use_internal_errors($prevErrors);
            $msg = $error ? trim($error->message) : 'Unknown XML parse error';
            throw new EppXmlException('Failed to parse EPP XML: ' . $msg);
        }

        libxml_use_internal_errors($prevErrors);
        return $dom;
    }

    private static function nodeText(DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        if ($nodes !== false && $nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return '';
    }

    private static function extractResData(DOMXPath $xpath, \DOMNode $resData): array
    {
        $data = [];
        $children = $xpath->query("./*", $resData);
        if ($children !== false) {
            foreach ($children as $child) {
                $nodeName = $child->localName;
                if ($nodeName === 'creData' || $nodeName === 'renData' || $nodeName === 'trnData' || $nodeName === 'infData') {
                    $subNodes = $xpath->query("./*", $child);
                    if ($subNodes !== false) {
                        foreach ($subNodes as $sub) {
                            $data[$sub->localName] = trim($sub->textContent);
                        }
                    }
                }
            }
        }
        return $data;
    }
}
