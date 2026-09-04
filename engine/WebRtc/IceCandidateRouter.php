<?php
declare(strict_types=1);

namespace Oshim\WebRtc;

use InvalidArgumentException;

/**
 * Trickle ICE Candidate Routing and Buffering Engine.
 * Manages asynchronous candidate exchange between peers before and after SDP negotiation.
 */
class IceCandidateRouter
{
    /** @var array<string, list<array>> */
    private array $bufferedCandidates = [];

    /**
     * Route an ICE candidate from one peer to another using an optional delivery callback.
     */
    public function routeCandidate(string $fromPeer, string $toPeer, array $candidate, ?callable $sendCallback = null): bool
    {
        if (!self::validateCandidate($candidate)) {
            return false;
        }

        if ($sendCallback !== null) {
            $result = $sendCallback($toPeer, $candidate, $fromPeer);
            return $result !== false;
        }

        return true;
    }

    /**
     * Buffer an ICE candidate for a session when target peer connection is not yet ready.
     */
    public function bufferCandidate(string $sessionId, array $candidate): void
    {
        if (!self::validateCandidate($candidate)) {
            throw new InvalidArgumentException("Invalid ICE candidate payload for session '{$sessionId}'.");
        }

        if (!isset($this->bufferedCandidates[$sessionId])) {
            $this->bufferedCandidates[$sessionId] = [];
        }

        $this->bufferedCandidates[$sessionId][] = $candidate;
    }

    /**
     * Flush all buffered candidates for a session to the callback handler.
     *
     * @return int Number of candidates successfully flushed
     */
    public function flushBufferedCandidates(string $sessionId, callable $sendCallback): int
    {
        if (!isset($this->bufferedCandidates[$sessionId])) {
            return 0;
        }

        $candidates = $this->bufferedCandidates[$sessionId];
        $count = 0;

        foreach ($candidates as $candidate) {
            $sendCallback($candidate);
            $count++;
        }

        unset($this->bufferedCandidates[$sessionId]);
        return $count;
    }

    /**
     * Get all buffered candidates for a session.
     *
     * @return list<array>
     */
    public function getBufferedCandidates(string $sessionId): array
    {
        return $this->bufferedCandidates[$sessionId] ?? [];
    }

    /**
     * Clear buffered candidates for a session.
     */
    public function clearSessionCandidates(string $sessionId): void
    {
        unset($this->bufferedCandidates[$sessionId]);
    }

    /**
     * Validate the structural integrity of an ICE candidate object.
     */
    public static function validateCandidate(array $candidate): bool
    {
        if (empty($candidate)) {
            return false;
        }

        // If wrapped in candidate envelope (e.g. ['candidate' => [...]])
        if (isset($candidate['candidate']) && is_array($candidate['candidate'])) {
            return self::validateCandidate($candidate['candidate']);
        }

        // Standard WebRTC candidate object format: { candidate: string, sdpMid?: string, sdpMLineIndex?: int }
        if (array_key_exists('candidate', $candidate)) {
            return is_string($candidate['candidate']);
        }

        // Alternative candidate format with component, foundation, protocol, ip, port
        if (isset($candidate['foundation'], $candidate['component'], $candidate['protocol'], $candidate['ip'], $candidate['port'])) {
            return true;
        }

        return false;
    }
}
