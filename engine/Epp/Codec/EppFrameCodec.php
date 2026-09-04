<?php
declare(strict_types=1);

namespace Oshim\Epp\Codec;

use Oshim\Epp\Exceptions\EppFramingException;
use Oshim\Epp\Exceptions\EppTransportException;

/**
 * RFC 5734 4-Byte Big-Endian Frame Codec.
 * Handles framing, unframing, and streaming I/O over stream socket resources.
 */
class EppFrameCodec
{
    /**
     * Prepends a 4-octet big-endian total length prefix (header + XML payload) to the XML string.
     */
    public static function pack(string $xml): string
    {
        $payloadLength = strlen($xml);
        $totalLength = $payloadLength + 4;
        return pack('N', $totalLength) . $xml;
    }

    /**
     * Unpacks a 4-byte big-endian framed binary string and returns the raw XML payload.
     *
     * @throws EppFramingException If the framed data is invalid or shorter than 4 bytes.
     */
    public static function unpack(string $framedData): string
    {
        $length = strlen($framedData);
        if ($length < 4) {
            throw new EppFramingException(
                sprintf("Invalid EPP frame: buffer too short (%d bytes, minimum 4 required).", $length)
            );
        }

        $totalLength = unpack('N', substr($framedData, 0, 4))[1];
        if ($totalLength < 4) {
            throw new EppFramingException(
                sprintf("Invalid EPP frame: declared total length (%d bytes) cannot be less than 4.", $totalLength)
            );
        }

        if ($length < $totalLength) {
            throw new EppFramingException(
                sprintf("Incomplete EPP frame: received %d bytes but declared length is %d bytes.", $length, $totalLength)
            );
        }

        return substr($framedData, 4, $totalLength - 4);
    }

    /**
     * Reads a complete EPP frame from a stream socket resource.
     *
     * @param resource $stream
     * @throws EppTransportException On socket errors, premature EOF, or timeout.
     * @throws EppFramingException If the declared length is invalid.
     */
    public static function readFromStream($stream, int $timeoutSeconds = 30): string
    {
        if (!is_resource($stream)) {
            throw new EppTransportException("Invalid socket stream resource.");
        }

        // 1. Read 4-byte big-endian header
        $header = '';
        while (strlen($header) < 4) {
            if (feof($stream)) {
                throw new EppTransportException("Connection closed by peer while reading EPP frame header.");
            }

            $chunk = @fread($stream, 4 - strlen($header));
            if ($chunk === false) {
                throw new EppTransportException("Socket read error while reading EPP frame header.");
            }

            if ($chunk === '') {
                $meta = stream_get_meta_data($stream);
                if (!empty($meta['timed_out'])) {
                    throw new EppTransportException("Socket timed out while waiting for EPP frame header.");
                }
                usleep(1000); // 1ms backoff
                continue;
            }

            $header .= $chunk;
        }

        $totalLength = unpack('N', $header)[1];
        if ($totalLength < 4) {
            throw new EppFramingException(
                sprintf("Invalid EPP frame: total length (%d) cannot be less than 4 bytes.", $totalLength)
            );
        }

        $payloadLength = $totalLength - 4;
        if ($payloadLength === 0) {
            return '';
        }

        // 2. Read exact payloadLength octets
        $payload = '';
        while (strlen($payload) < $payloadLength) {
            if (feof($stream)) {
                throw new EppTransportException("Connection prematurely closed by peer while reading EPP payload.");
            }

            $bytesNeeded = min(8192, $payloadLength - strlen($payload));
            $chunk = @fread($stream, $bytesNeeded);
            if ($chunk === false) {
                throw new EppTransportException("Socket read error while reading EPP payload.");
            }

            if ($chunk === '') {
                $meta = stream_get_meta_data($stream);
                if (!empty($meta['timed_out'])) {
                    throw new EppTransportException("Socket timed out while reading EPP payload.");
                }
                usleep(1000);
                continue;
            }

            $payload .= $chunk;
        }

        return $payload;
    }

    /**
     * Writes an EPP frame to a stream socket resource.
     *
     * @param resource $stream
     * @throws EppTransportException On write failure.
     */
    public static function writeToStream($stream, string $xml): void
    {
        if (!is_resource($stream)) {
            throw new EppTransportException("Invalid socket stream resource.");
        }

        $frame = self::pack($xml);
        $totalBytes = strlen($frame);
        $bytesWritten = 0;

        while ($bytesWritten < $totalBytes) {
            $chunk = @fwrite($stream, substr($frame, $bytesWritten));
            if ($chunk === false || $chunk === 0) {
                throw new EppTransportException("Failed to write EPP frame to socket stream.");
            }
            $bytesWritten += $chunk;
        }

        @fflush($stream);
    }
}
