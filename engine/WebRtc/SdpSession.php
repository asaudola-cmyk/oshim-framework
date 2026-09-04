<?php
declare(strict_types=1);

namespace Oshim\WebRtc;

use InvalidArgumentException;

/**
 * Sovereign Session Description Protocol (SDP) Engine & Offer/Answer State Machine.
 * Validates RFC 4566 compliance and negotiates real-time audio/video codec capabilities.
 */
class SdpSession
{
    public const STATE_NEW             = 'NEW';
    public const STATE_OFFER_CREATED   = 'OFFER_CREATED';
    public const STATE_OFFER_SENT      = 'OFFER_SENT';
    public const STATE_ANSWER_RECEIVED = 'ANSWER_RECEIVED';
    public const STATE_ESTABLISHED     = 'ESTABLISHED';
    public const STATE_CLOSED          = 'CLOSED';

    public string $sessionId;
    public string $initiatorPeerId;
    public string $targetPeerId;
    public ?string $offerSdp = null;
    public ?string $answerSdp = null;
    public string $state = self::STATE_NEW;
    public float $createdAt;
    public ?float $establishedAt = null;
    public array $attributes = [];

    public function __construct(
        string $sessionId,
        string $initiatorPeerId,
        string $targetPeerId,
        ?string $offerSdp = null,
        ?string $answerSdp = null,
        string $state = self::STATE_NEW,
        float $createdAt = 0.0,
        ?float $establishedAt = null,
        array $attributes = []
    ) {
        $this->sessionId = $sessionId;
        $this->initiatorPeerId = $initiatorPeerId;
        $this->targetPeerId = $targetPeerId;
        $this->state = $state;
        $this->createdAt = $createdAt > 0.0 ? $createdAt : microtime(true);
        $this->establishedAt = $establishedAt;
        $this->attributes = $attributes;

        if ($offerSdp !== null) {
            $this->setOffer($offerSdp);
        }
        if ($answerSdp !== null) {
            $this->setAnswer($answerSdp);
        }
    }

    /**
     * Set and validate the SDP Offer for this session.
     */
    public function setOffer(string $sdp): void
    {
        if (!self::isValidSdp($sdp)) {
            throw new InvalidArgumentException("Invalid RFC 4566 SDP offer provided for session '{$this->sessionId}'.");
        }

        $this->offerSdp = trim($sdp);
        if ($this->state === self::STATE_NEW || $this->state === self::STATE_OFFER_CREATED) {
            $this->state = self::STATE_OFFER_SENT;
        }
    }

    /**
     * Set and validate the SDP Answer for this session and transition to ESTABLISHED.
     */
    public function setAnswer(string $sdp): void
    {
        if (!self::isValidSdp($sdp)) {
            throw new InvalidArgumentException("Invalid RFC 4566 SDP answer provided for session '{$this->sessionId}'.");
        }

        $this->answerSdp = trim($sdp);
        $this->state = self::STATE_ESTABLISHED;
        $this->establishedAt = microtime(true);
    }

    /**
     * Close the SDP session.
     */
    public function close(): void
    {
        $this->state = self::STATE_CLOSED;
    }

    /**
     * Check if the peer-to-peer session is established.
     */
    public function isEstablished(): bool
    {
        return $this->state === self::STATE_ESTABLISHED;
    }

    /**
     * Validate RFC 4566 compliance of an SDP string.
     */
    public static function isValidSdp(string $sdp): bool
    {
        $sdp = trim($sdp);
        if ($sdp === '') {
            return false;
        }

        $lines = preg_split('/\r\n|\r|\n/', $sdp);
        if ($lines === false || count($lines) === 0) {
            return false;
        }

        $hasVersion = false;
        $hasOrigin = false;
        $hasSessionName = false;
        $hasTime = false;
        $hasMedia = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (!preg_match('/^[a-zA-Z]=.*$/', $line)) {
                return false;
            }

            $prefix = substr($line, 0, 2);
            switch ($prefix) {
                case 'v=':
                    if ($line === 'v=0') {
                        $hasVersion = true;
                    }
                    break;
                case 'o=':
                    $hasOrigin = true;
                    break;
                case 's=':
                    $hasSessionName = true;
                    break;
                case 't=':
                    $hasTime = true;
                    break;
                case 'm=':
                    $hasMedia = true;
                    break;
            }
        }

        return $hasVersion && $hasOrigin && $hasSessionName && $hasTime && $hasMedia;
    }

    /**
     * Parse and extract media capabilities, codecs, ICE credentials, and fingerprints from SDP.
     *
     * @return array<string, mixed>
     */
    public static function extractMediaCapabilities(string $sdp): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($sdp));
        if ($lines === false) {
            return [];
        }

        $session = [];
        $mediaSections = [];
        $currentMedia = null;
        $iceUfrag = null;
        $icePwd = null;
        $fingerprint = null;
        $codecs = ['audio' => [], 'video' => [], 'application' => []];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $type = substr($line, 0, 1);
            $val = substr($line, 2);

            if ($type === 'v') {
                $session['version'] = (int)$val;
            } elseif ($type === 'o') {
                $session['origin'] = $val;
            } elseif ($type === 's') {
                $session['name'] = $val;
            } elseif ($type === 't') {
                $session['time'] = $val;
            } elseif ($type === 'm') {
                if ($currentMedia !== null) {
                    $mediaSections[] = $currentMedia;
                }
                $parts = explode(' ', $val);
                $mediaType = $parts[0] ?? 'unknown';
                $port = isset($parts[1]) ? (int)$parts[1] : 0;
                $proto = $parts[2] ?? '';
                $formats = array_slice($parts, 3);

                $currentMedia = [
                    'type' => $mediaType,
                    'port' => $port,
                    'protocol' => $proto,
                    'formats' => $formats,
                    'direction' => 'sendrecv',
                    'mid' => null,
                    'rtp' => [],
                    'fmtp' => [],
                    'rtcpFb' => [],
                    'ssrc' => [],
                ];
            } elseif ($type === 'a') {
                if (str_starts_with($val, 'ice-ufrag:')) {
                    $iceUfrag = substr($val, 10);
                } elseif (str_starts_with($val, 'ice-pwd:')) {
                    $icePwd = substr($val, 8);
                } elseif (str_starts_with($val, 'fingerprint:')) {
                    $fpParts = explode(' ', substr($val, 12), 2);
                    $fingerprint = [
                        'algorithm' => $fpParts[0] ?? 'sha-256',
                        'hash' => $fpParts[1] ?? '',
                    ];
                }

                if ($currentMedia !== null) {
                    if (in_array($val, ['sendrecv', 'sendonly', 'recvonly', 'inactive'], true)) {
                        $currentMedia['direction'] = $val;
                    } elseif (str_starts_with($val, 'mid:')) {
                        $currentMedia['mid'] = substr($val, 4);
                    } elseif (str_starts_with($val, 'rtpmap:')) {
                        $rtpStr = substr($val, 7);
                        [$payloadType, $codecInfo] = explode(' ', $rtpStr, 2) + ['', ''];
                        $codecParts = explode('/', $codecInfo);
                        $codecName = $codecParts[0] ?? '';
                        $clockRate = isset($codecParts[1]) ? (int)$codecParts[1] : null;
                        $channels = isset($codecParts[2]) ? (int)$codecParts[2] : null;

                        $currentMedia['rtp'][$payloadType] = [
                            'codec' => $codecName,
                            'clockRate' => $clockRate,
                            'channels' => $channels,
                        ];

                        if (isset($codecs[$currentMedia['type']]) && !in_array($codecName, $codecs[$currentMedia['type']], true)) {
                            $codecs[$currentMedia['type']][] = $codecName;
                        }
                    } elseif (str_starts_with($val, 'fmtp:')) {
                        $fmtpStr = substr($val, 5);
                        [$payloadType, $params] = explode(' ', $fmtpStr, 2) + ['', ''];
                        $currentMedia['fmtp'][$payloadType] = $params;
                    } elseif (str_starts_with($val, 'rtcp-fb:')) {
                        $fbStr = substr($val, 8);
                        [$payloadType, $param] = explode(' ', $fbStr, 2) + ['', ''];
                        $currentMedia['rtcpFb'][$payloadType][] = $param;
                    } elseif (str_starts_with($val, 'ssrc:')) {
                        $currentMedia['ssrc'][] = substr($val, 5);
                    }
                }
            }
        }

        if ($currentMedia !== null) {
            $mediaSections[] = $currentMedia;
        }

        return [
            'session' => $session,
            'media' => $mediaSections,
            'codecs' => array_filter($codecs),
            'ice' => [
                'ufrag' => $iceUfrag,
                'pwd' => $icePwd,
            ],
            'fingerprint' => $fingerprint,
            'raw' => $sdp,
        ];
    }

    /**
     * Export session description as structured array.
     */
    public function toArray(): array
    {
        return [
            'sessionId' => $this->sessionId,
            'initiatorPeerId' => $this->initiatorPeerId,
            'targetPeerId' => $this->targetPeerId,
            'state' => $this->state,
            'hasOffer' => $this->offerSdp !== null,
            'hasAnswer' => $this->answerSdp !== null,
            'createdAt' => $this->createdAt,
            'establishedAt' => $this->establishedAt,
            'attributes' => $this->attributes,
        ];
    }
}
