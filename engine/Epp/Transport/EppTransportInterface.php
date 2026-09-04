<?php
declare(strict_types=1);

namespace Oshim\Epp\Transport;

use Oshim\Epp\Exceptions\EppTransportException;

/**
 * Interface contract for EPP transport mechanisms (RFC 5734, Mock, etc.).
 */
interface EppTransportInterface
{
    /**
     * Connects to the EPP registry and returns the initial server greeting XML.
     *
     * @throws EppTransportException If connection fails.
     */
    public function connect(): string;

    /**
     * Sends an EPP XML command to the registry.
     *
     * @throws EppTransportException If the socket is disconnected or write fails.
     */
    public function send(string $xml): void;

    /**
     * Receives an EPP XML response from the registry.
     *
     * @throws EppTransportException If the socket is disconnected or read fails.
     */
    public function receive(): string;

    /**
     * Convenience method to send an EPP XML command and immediately receive the response.
     */
    public function sendAndReceive(string $xml): string;

    /**
     * Closes the connection.
     */
    public function disconnect(): void;

    /**
     * Returns whether the transport currently has an active connection.
     */
    public function isConnected(): bool;
}
