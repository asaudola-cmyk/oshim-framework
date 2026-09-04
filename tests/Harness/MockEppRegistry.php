<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use DOMDocument;
use SimpleXMLElement;
use RuntimeException;

/**
 * Lightweight RFC 5734 4-byte framed EPP TLS/TCP simulator and XML envelope processor.
 */
class MockEppRegistry
{
    private array $domains = [];
    private array $hosts = [];
    private array $contacts = [];
    private array $authenticatedSessions = [];
    private array $commandHistory = [];
    private array $injectedFailures = [];
    private $serverSocket = null;
    private int $port = 0;
    private array $clientSockets = [];

    public function __construct(int $port = 0)
    {
        $this->port = $port;
        $this->resetState();
    }

    public function resetState(): void
    {
        $this->domains = [];
        $this->hosts = [];
        $this->contacts = [];
        $this->authenticatedSessions = [];
        $this->commandHistory = [];
        $this->injectedFailures = [];
    }

    /**
     * Injects an error response for a specific EPP command name (e.g. 'check', 'create', 'renew').
     */
    public function injectFailure(string $command, int $resultCode, string $message): void
    {
        $this->injectedFailures[strtolower($command)] = [
            'code' => $resultCode,
            'message' => $message,
        ];
    }

    public function clearFailures(): void
    {
        $this->injectedFailures = [];
    }

    public function getLastCommandXml(): ?string
    {
        return !empty($this->commandHistory) ? end($this->commandHistory) : null;
    }

    public function getCommandHistory(): array
    {
        return $this->commandHistory;
    }

    public function getDomain(string $name): ?array
    {
        return $this->domains[strtolower(trim($name))] ?? null;
    }

    public function getHost(string $name): ?array
    {
        return $this->hosts[strtolower(trim($name))] ?? null;
    }

    /**
     * In-memory framed binary dispatch (RFC 5734).
     */
    public function dispatch(string $framedRequest): string
    {
        if (strlen($framedRequest) < 4) {
            throw new RuntimeException("Invalid EPP frame: less than 4 bytes length header.");
        }

        $totalLength = unpack('N', substr($framedRequest, 0, 4))[1];
        if ($totalLength < 4) {
            throw new RuntimeException("Invalid EPP frame: total length cannot be less than 4 bytes.");
        }
        $xmlPayload = substr($framedRequest, 4, $totalLength - 4);

        $responseXml = $this->handleCommand($xmlPayload);
        return $this->frameXml($responseXml);
    }

    /**
     * Frames an XML document with a 4-byte big-endian length prefix.
     */
    public function frameXml(string $xml): string
    {
        $payloadLength = strlen($xml);
        $totalLength = $payloadLength + 4;
        return pack('N', $totalLength) . $xml;
    }

    /**
     * Unframes an EPP binary packet.
     */
    public function unframeXml(string $framed): string
    {
        if (strlen($framed) < 4) {
            throw new RuntimeException("Invalid EPP frame: less than 4 bytes length header.");
        }
        $totalLength = unpack('N', substr($framed, 0, 4))[1];
        if ($totalLength < 4) {
            throw new RuntimeException("Invalid EPP frame: total length cannot be less than 4 bytes.");
        }
        return substr($framed, 4, $totalLength - 4);
    }

    public function generateGreeting(): string
    {
        $date = gmdate('Y-m-d\TH:i:s\Z');
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <greeting>
    <svID>OSHIM-MOCK-EPP-REGISTRY-v1.0</svID>
    <svDate>{$date}</svDate>
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

    public function handleCommand(string $xml): string
    {
        $this->commandHistory[] = $xml;
        $cleanXml = trim($xml);
        $clTrID = 'TRID-' . bin2hex(random_bytes(4));
        $svTrID = 'SVR-' . bin2hex(random_bytes(6));

        // Extract clTRID if present
        if (preg_match('/<clTRID>(.*?)<\/clTRID>/s', $cleanXml, $m)) {
            $clTrID = trim($m[1]);
        }

        // Check for injected failures
        foreach ($this->injectedFailures as $cmd => $fail) {
            if (stripos($cleanXml, "<{$cmd}") !== false || stripos($cleanXml, ":{$cmd}") !== false) {
                return $this->buildResponseXml($fail['code'], $fail['message'], '', $clTrID, $svTrID);
            }
        }

        // 1. Login Command
        if (str_contains($cleanXml, '<login') || str_contains($cleanXml, ':login')) {
            preg_match('/<clID>(.*?)<\/clID>/s', $cleanXml, $clMatch);
            preg_match('/<pw>(.*?)<\/pw>/s', $cleanXml, $pwMatch);
            $clID = trim($clMatch[1] ?? 'OSHIM_REGISTRAR');
            $pw = trim($pwMatch[1] ?? '');

            if ($pw === 'invalid_pw' || $pw === 'wrong') {
                return $this->buildResponseXml(2200, 'Authentication error', '', $clTrID, $svTrID);
            }

            $this->authenticatedSessions[$clID] = true;
            return $this->buildResponseXml(1000, 'Command completed successfully', '', $clTrID, $svTrID);
        }

        // 2. Logout Command
        if (str_contains($cleanXml, '<logout') || str_contains($cleanXml, ':logout')) {
            $this->authenticatedSessions = [];
            return $this->buildResponseXml(1500, 'Command completed successfully; ending session', '', $clTrID, $svTrID);
        }

        // 3. Domain Check
        if (str_contains($cleanXml, '<domain:check') || (str_contains($cleanXml, '<check>') && str_contains($cleanXml, 'domain'))) {
            preg_match_all('/<domain:name.*?>(.*?)<\/domain:name>/s', $cleanXml, $matches);
            $domainNames = $matches[1] ?? [];

            $resData = "<domain:chkData xmlns:domain=\"urn:ietf:params:xml:ns:domain-1.0\">\n";
            foreach ($domainNames as $name) {
                $name = strtolower(trim($name));
                $isAvail = !isset($this->domains[$name]);
                $availStr = $isAvail ? '1' : '0';
                $reason = $isAvail ? '' : "\n        <domain:reason>In use</domain:reason>";
                $resData .= "      <domain:cd>\n        <domain:name avail=\"{$availStr}\">{$name}</domain:name>{$reason}\n      </domain:cd>\n";
            }
            $resData .= "    </domain:chkData>";

            return $this->buildResponseXml(1000, 'Command completed successfully', $resData, $clTrID, $svTrID);
        }

        // 4. Host Check
        if (str_contains($cleanXml, '<host:check') || (str_contains($cleanXml, '<check>') && str_contains($cleanXml, 'host'))) {
            preg_match_all('/<host:name.*?>(.*?)<\/host:name>/s', $cleanXml, $matches);
            $hostNames = $matches[1] ?? [];

            $resData = "<host:chkData xmlns:host=\"urn:ietf:params:xml:ns:host-1.0\">\n";
            foreach ($hostNames as $name) {
                $name = strtolower(trim($name));
                $isAvail = !isset($this->hosts[$name]);
                $availStr = $isAvail ? '1' : '0';
                $resData .= "      <host:cd>\n        <host:name avail=\"{$availStr}\">{$name}</host:name>\n      </host:cd>\n";
            }
            $resData .= "    </host:chkData>";

            return $this->buildResponseXml(1000, 'Command completed successfully', $resData, $clTrID, $svTrID);
        }

        // 5. Host Create (check before domain to prevent sub-string collisions)
        if (str_contains($cleanXml, '<host:create')) {
            preg_match('/<host:name.*?>(.*?)<\/host:name>/s', $cleanXml, $nameMatch);
            $hostName = strtolower(trim($nameMatch[1] ?? ''));

            if ($hostName === '') {
                return $this->buildResponseXml(2005, 'Parameter value syntax error', '', $clTrID, $svTrID);
            }

            if (isset($this->hosts[$hostName])) {
                return $this->buildResponseXml(2302, 'Object exists', '', $clTrID, $svTrID);
            }

            preg_match_all('/<host:addr[^>]*ip="v4"[^>]*>(.*?)<\/host:addr>/s', $cleanXml, $ipv4Matches);
            preg_match_all('/<host:addr[^>]*ip="v6"[^>]*>(.*?)<\/host:addr>/s', $cleanXml, $ipv6Matches);

            $crDate = gmdate('Y-m-d\TH:i:s\Z');
            $this->hosts[$hostName] = [
                'name' => $hostName,
                'ipv4' => $ipv4Matches[1] ?? [],
                'ipv6' => $ipv6Matches[1] ?? [],
                'status' => 'ok',
                'crDate' => $crDate,
            ];

            $resData = <<<XML
<host:creData xmlns:host="urn:ietf:params:xml:ns:host-1.0">
      <host:name>{$hostName}</host:name>
      <host:crDate>{$crDate}</host:crDate>
    </host:creData>
XML;
            return $this->buildResponseXml(1000, 'Command completed successfully', $resData, $clTrID, $svTrID);
        }

        // 6. Domain Create
        if (str_contains($cleanXml, '<domain:create')) {
            preg_match('/<domain:name.*?>(.*?)<\/domain:name>/s', $cleanXml, $nameMatch);
            $domainName = strtolower(trim($nameMatch[1] ?? ''));

            if ($domainName === '') {
                return $this->buildResponseXml(2005, 'Parameter value syntax error', '', $clTrID, $svTrID);
            }

            if (isset($this->domains[$domainName])) {
                return $this->buildResponseXml(2302, 'Object exists', '', $clTrID, $svTrID);
            }

            preg_match('/<domain:period.*?>(.*?)<\/domain:period>/s', $cleanXml, $periodMatch);
            $period = (int)($periodMatch[1] ?? 1);
            if ($period <= 0) $period = 1;

            preg_match_all('/<domain:hostObj.*?>(.*?)<\/domain:hostObj>/s', $cleanXml, $nsMatches);
            $nameservers = $nsMatches[1] ?? ['ns1.oshim.cloud', 'ns2.oshim.cloud'];

            preg_match('/<domain:registrant.*?>(.*?)<\/domain:registrant>/s', $cleanXml, $regMatch);
            $registrant = trim($regMatch[1] ?? 'CONTACT-1');

            preg_match('/<domain:authInfo>.*?<domain:pw.*?>(.*?)<\/domain:pw>.*?<\/domain:authInfo>/s', $cleanXml, $authMatch);
            $authInfo = trim($authMatch[1] ?? 'AuthPassword123!');

            $crDate = gmdate('Y-m-d\TH:i:s\Z');
            $exDate = gmdate('Y-m-d\TH:i:s\Z', strtotime("+{$period} years"));

            $this->domains[$domainName] = [
                'name' => $domainName,
                'period' => $period,
                'nameservers' => $nameservers,
                'registrant' => $registrant,
                'authInfo' => $authInfo,
                'status' => 'ok',
                'crDate' => $crDate,
                'exDate' => $exDate,
                'clID' => 'OSHIM_REGISTRAR',
            ];

            $resData = <<<XML
<domain:creData xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
      <domain:name>{$domainName}</domain:name>
      <domain:crDate>{$crDate}</domain:crDate>
      <domain:exDate>{$exDate}</domain:exDate>
    </domain:creData>
XML;
            return $this->buildResponseXml(1000, 'Command completed successfully', $resData, $clTrID, $svTrID);
        }

        // 6. Host Create
        if (str_contains($cleanXml, '<host:create') || (str_contains($cleanXml, '<create>') && str_contains($cleanXml, 'host'))) {
            preg_match('/<host:name.*?>(.*?)<\/host:name>/s', $cleanXml, $nameMatch);
            $hostName = strtolower(trim($nameMatch[1] ?? ''));

            if ($hostName === '') {
                return $this->buildResponseXml(2005, 'Parameter value syntax error', '', $clTrID, $svTrID);
            }

            if (isset($this->hosts[$hostName])) {
                return $this->buildResponseXml(2302, 'Object exists', '', $clTrID, $svTrID);
            }

            preg_match_all('/<host:addr[^>]*ip="v4"[^>]*>(.*?)<\/host:addr>/s', $cleanXml, $ipv4Matches);
            preg_match_all('/<host:addr[^>]*ip="v6"[^>]*>(.*?)<\/host:addr>/s', $cleanXml, $ipv6Matches);

            $crDate = gmdate('Y-m-d\TH:i:s\Z');
            $this->hosts[$hostName] = [
                'name' => $hostName,
                'ipv4' => $ipv4Matches[1] ?? [],
                'ipv6' => $ipv6Matches[1] ?? [],
                'status' => 'ok',
                'crDate' => $crDate,
            ];

            $resData = <<<XML
<host:creData xmlns:host="urn:ietf:params:xml:ns:host-1.0">
      <host:name>{$hostName}</host:name>
      <host:crDate>{$crDate}</host:crDate>
    </host:creData>
XML;
            return $this->buildResponseXml(1000, 'Command completed successfully', $resData, $clTrID, $svTrID);
        }

        // 7. Domain Info
        if (str_contains($cleanXml, '<domain:info') || (str_contains($cleanXml, '<info>') && str_contains($cleanXml, 'domain'))) {
            preg_match('/<domain:name.*?>(.*?)<\/domain:name>/s', $cleanXml, $nameMatch);
            $domainName = strtolower(trim($nameMatch[1] ?? ''));

            if (!isset($this->domains[$domainName])) {
                return $this->buildResponseXml(2303, 'Object does not exist', '', $clTrID, $svTrID);
            }

            $dom = $this->domains[$domainName];
            $nsXml = '';
            foreach ($dom['nameservers'] as $ns) {
                $nsXml .= "        <domain:hostObj>{$ns}</domain:hostObj>\n";
            }

            $resData = <<<XML
<domain:infData xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
      <domain:name>{$dom['name']}</domain:name>
      <domain:roid>DOM-{$domainName}-OSHIM</domain:roid>
      <domain:status s="{$dom['status']}"/>
      <domain:registrant>{$dom['registrant']}</domain:registrant>
      <domain:ns>
{$nsXml}      </domain:ns>
      <domain:clID>{$dom['clID']}</domain:clID>
      <domain:crDate>{$dom['crDate']}</domain:crDate>
      <domain:exDate>{$dom['exDate']}</domain:exDate>
      <domain:authInfo>
        <domain:pw>{$dom['authInfo']}</domain:pw>
      </domain:authInfo>
    </domain:infData>
XML;
            return $this->buildResponseXml(1000, 'Command completed successfully', $resData, $clTrID, $svTrID);
        }

        // 8. Domain Renew
        if (str_contains($cleanXml, '<domain:renew') || (str_contains($cleanXml, '<renew>') && str_contains($cleanXml, 'domain'))) {
            preg_match('/<domain:name.*?>(.*?)<\/domain:name>/s', $cleanXml, $nameMatch);
            $domainName = strtolower(trim($nameMatch[1] ?? ''));

            if (!isset($this->domains[$domainName])) {
                return $this->buildResponseXml(2303, 'Object does not exist', '', $clTrID, $svTrID);
            }

            preg_match('/<domain:period.*?>(.*?)<\/domain:period>/s', $cleanXml, $periodMatch);
            $years = (int)($periodMatch[1] ?? 1);
            if ($years <= 0) $years = 1;

            $currentExDate = strtotime($this->domains[$domainName]['exDate']);
            $newExDate = gmdate('Y-m-d\TH:i:s\Z', strtotime("+{$years} years", $currentExDate));
            $this->domains[$domainName]['exDate'] = $newExDate;

            $resData = <<<XML
<domain:renData xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
      <domain:name>{$domainName}</domain:name>
      <domain:exDate>{$newExDate}</domain:exDate>
    </domain:renData>
XML;
            return $this->buildResponseXml(1000, 'Command completed successfully', $resData, $clTrID, $svTrID);
        }

        // 9. Domain Transfer
        if (str_contains($cleanXml, '<domain:transfer') || str_contains($cleanXml, '<transfer op=') || str_contains($cleanXml, '<transfer>')) {
            preg_match('/<domain:name.*?>(.*?)<\/domain:name>/s', $cleanXml, $nameMatch);
            $domainName = strtolower(trim($nameMatch[1] ?? ''));

            if (!isset($this->domains[$domainName])) {
                return $this->buildResponseXml(2303, 'Object does not exist', '', $clTrID, $svTrID);
            }

            preg_match('/<domain:authInfo>.*?<domain:pw.*?>(.*?)<\/domain:pw>.*?<\/domain:authInfo>/s', $cleanXml, $authMatch);
            $authPw = trim($authMatch[1] ?? '');

            if ($authPw !== '' && $authPw !== $this->domains[$domainName]['authInfo']) {
                return $this->buildResponseXml(2202, 'Invalid authorization information', '', $clTrID, $svTrID);
            }

            $reDate = gmdate('Y-m-d\TH:i:s\Z');
            $resData = <<<XML
<domain:trnData xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
      <domain:name>{$domainName}</domain:name>
      <domain:trStatus>pending</domain:trStatus>
      <domain:reID>OSHIM_TRANSFER</domain:reID>
      <domain:reDate>{$reDate}</domain:reDate>
      <domain:acID>OSHIM_REGISTRAR</domain:acID>
      <domain:acDate>{$reDate}</domain:acDate>
    </domain:trnData>
XML;
            return $this->buildResponseXml(1001, 'Command completed successfully; action pending', $resData, $clTrID, $svTrID);
        }

        // 10. Domain Update
        if (str_contains($cleanXml, '<domain:update') || (str_contains($cleanXml, '<update>') && str_contains($cleanXml, 'domain'))) {
            preg_match('/<domain:name.*?>(.*?)<\/domain:name>/s', $cleanXml, $nameMatch);
            $domainName = strtolower(trim($nameMatch[1] ?? ''));

            if (!isset($this->domains[$domainName])) {
                return $this->buildResponseXml(2303, 'Object does not exist', '', $clTrID, $svTrID);
            }

            // Process added nameservers
            if (preg_match('/<domain:add>.*?<domain:ns>(.*?)<\/domain:ns>.*?<\/domain:add>/s', $cleanXml, $addNsMatch)) {
                preg_match_all('/<domain:hostObj.*?>(.*?)<\/domain:hostObj>/s', $addNsMatch[1], $addMatches);
                foreach ($addMatches[1] as $newNs) {
                    $newNs = trim($newNs);
                    if (!in_array($newNs, $this->domains[$domainName]['nameservers'], true)) {
                        $this->domains[$domainName]['nameservers'][] = $newNs;
                    }
                }
            }

            // Process removed nameservers
            if (preg_match('/<domain:rem>.*?<domain:ns>(.*?)<\/domain:ns>.*?<\/domain:rem>/s', $cleanXml, $remNsMatch)) {
                preg_match_all('/<domain:hostObj.*?>(.*?)<\/domain:hostObj>/s', $remNsMatch[1], $remMatches);
                $this->domains[$domainName]['nameservers'] = array_values(array_diff($this->domains[$domainName]['nameservers'], $remMatches[1]));
            }

            return $this->buildResponseXml(1000, 'Command completed successfully', '', $clTrID, $svTrID);
        }

        // Default OK response
        return $this->buildResponseXml(1000, 'Command completed successfully', '', $clTrID, $svTrID);
    }

    /**
     * Starts an ephemeral TCP socket server for live socket EPP testing.
     */
    public function startServer(string $host = '127.0.0.1', int $port = 0): int
    {
        $context = stream_context_create();
        $this->serverSocket = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        if (!$this->serverSocket) {
            throw new RuntimeException("Failed to start mock EPP socket server: {$errstr} ({$errno})");
        }

        stream_set_blocking($this->serverSocket, false);
        $name = stream_socket_get_name($this->serverSocket, false);
        $this->port = (int)substr(strrchr($name, ':'), 1);

        return $this->port;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Non-blocking socket tick to accept clients and process frames.
     */
    public function tick(): void
    {
        if (!$this->serverSocket) {
            return;
        }

        // Accept new connections
        $newClient = @stream_socket_accept($this->serverSocket, 0);
        if ($newClient) {
            stream_set_blocking($newClient, false);
            // Send greeting upon connection
            $greeting = $this->generateGreeting();
            fwrite($newClient, $this->frameXml($greeting));
            $this->clientSockets[] = $newClient;
        }

        // Read and process client frames
        foreach ($this->clientSockets as $idx => $client) {
            if (feof($client)) {
                fclose($client);
                unset($this->clientSockets[$idx]);
                continue;
            }

            $hdr = @fread($client, 4);
            if ($hdr !== false && strlen($hdr) === 4) {
                $len = unpack('N', $hdr)[1];
                $payload = '';
                $remaining = $len - 4;
                while ($remaining > 0 && !feof($client)) {
                    $chunk = fread($client, min($remaining, 8192));
                    if ($chunk === false || $chunk === '') break;
                    $payload .= $chunk;
                    $remaining -= strlen($chunk);
                }

                $respXml = $this->handleCommand($payload);
                fwrite($client, $this->frameXml($respXml));
            }
        }
    }

    public function stopServer(): void
    {
        foreach ($this->clientSockets as $socket) {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
        $this->clientSockets = [];

        if ($this->serverSocket && is_resource($this->serverSocket)) {
            fclose($this->serverSocket);
            $this->serverSocket = null;
        }
    }

    public function __destruct()
    {
        $this->stopServer();
    }

    private function buildResponseXml(int $code, string $msg, string $resData, string $clTrID, string $svTrID): string
    {
        $resDataBlock = $resData !== '' ? "\n    <resData>\n      {$resData}\n    </resData>" : '';
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <response>
    <result code="{$code}">
      <msg>{$msg}</msg>
    </result>{$resDataBlock}
    <trID>
      <clTRID>{$clTrID}</clTRID>
      <svTRID>{$svTrID}</svTRID>
    </trID>
  </response>
</epp>
XML;
    }
}
