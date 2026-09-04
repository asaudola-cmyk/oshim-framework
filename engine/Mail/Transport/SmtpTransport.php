<?php
declare(strict_types=1);

namespace Oshim\Mail\Transport;

use Oshim\Mail\Mailable;
use RuntimeException;

/**
 * Pure PHP Zero-Dependency Socket SMTP Client with STARTTLS, AUTH LOGIN, and RFC 5322 MIME compilation.
 */
class SmtpTransport implements TransportInterface
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private int $timeout;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 587,
        string $username = '',
        string $password = '',
        string $encryption = 'tls',
        int $timeout = 10
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = strtolower($encryption);
        $this->timeout = $timeout;
    }

    public function send(Mailable $mailable): bool
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout);

        if (!$socket) {
            throw new RuntimeException("SMTP Connection failed: {$errstr} ({$errno})");
        }

        try {
            $this->readResponse($socket); // 220 banner

            $this->sendCommand($socket, "EHLO " . gethostname());

            if ($this->encryption === 'tls') {
                $this->sendCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                $this->sendCommand($socket, "EHLO " . gethostname());
            }

            if (!empty($this->username) && !empty($this->password)) {
                $this->sendCommand($socket, "AUTH LOGIN");
                $this->sendCommand($socket, base64_encode($this->username));
                $this->sendCommand($socket, base64_encode($this->password));
            }

            $this->sendCommand($socket, "MAIL FROM:<{$mailable->getFrom()}>");

            foreach ($mailable->getTo() as $recipient) {
                $this->sendCommand($socket, "RCPT TO:<{$recipient['email']}>");
            }

            $this->sendCommand($socket, "DATA");

            // Build MIME message
            $mime = $this->buildMimeMessage($mailable);
            fwrite($socket, $mime . "\r\n.\r\n");
            $this->readResponse($socket);

            $this->sendCommand($socket, "QUIT");
            return true;
        } finally {
            @fclose($socket);
        }
    }

    private function sendCommand($socket, string $cmd): string
    {
        fwrite($socket, $cmd . "\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = (int)substr($response, 0, 3);
        if ($code >= 400) {
            throw new RuntimeException("SMTP Server Error: {$response}");
        }
        return $response;
    }

    private function buildMimeMessage(Mailable $mail): string
    {
        $boundary = "----=_Part_" . md5(uniqid('', true));
        $toHeaders = [];
        foreach ($mail->getTo() as $t) {
            $toHeaders[] = empty($t['name']) ? "<{$t['email']}>" : "{$t['name']} <{$t['email']}>";
        }

        $headers = [
            "From: {$mail->getFromName()} <{$mail->getFrom()}>",
            "To: " . implode(', ', $toHeaders),
            "Subject: =?UTF-8?B?" . base64_encode($mail->getSubject()) . "?=",
            "Date: " . date(DATE_RFC2822),
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];

        $body = implode("\r\n", $headers) . "\r\n\r\n";

        // Plain text part
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($mail->getText())) . "\r\n";

        // HTML part
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($mail->getHtml())) . "\r\n";

        $body .= "--{$boundary}--\r\n";
        return $body;
    }
}
