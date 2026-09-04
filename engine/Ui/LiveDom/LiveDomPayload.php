<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use Oshim\Ui\LiveDom\Exceptions\InvalidSignatureException;
use JsonSerializable;

/**
 * Encapsulates the cryptographically signed component state snapshot.
 * Prevents client-side tampering using HMAC-SHA256 checksum verification.
 */
class LiveDomPayload implements JsonSerializable
{
    protected static ?string $defaultSecret = null;

    public function __construct(
        protected string $id,
        protected string $component,
        protected array $state,
        protected string $checksum,
        protected array $memo = []
    ) {
    }

    public static function setDefaultSecret(string $secret): void
    {
        self::$defaultSecret = $secret;
    }

    public static function getDefaultSecret(): string
    {
        if (self::$defaultSecret !== null) {
            return self::$defaultSecret;
        }

        $key = $_ENV['APP_KEY'] ?? $_ENV['OSHIM_KEY'] ?? getenv('APP_KEY') ?: 'oshim_livedom_sovereign_secret_key';
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }

    public static function generateChecksum(string $id, array $state, ?string $secret = null): string
    {
        $secretKey = $secret ?? self::getDefaultSecret();
        $serialized = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash_hmac('sha256', $id . ':' . (string)$serialized, $secretKey);
    }

    public static function create(string $id, string $component, array $state, array $memo = [], ?string $secret = null): static
    {
        $checksum = self::generateChecksum($id, $state, $secret);
        return new static($id, $component, $state, $checksum, $memo);
    }

    public static function fromArray(array $data, ?string $secret = null, bool $verify = true): static
    {
        $id = (string)($data['id'] ?? '');
        $component = (string)($data['component'] ?? ($data['class'] ?? ''));
        $state = (array)($data['state'] ?? []);
        $checksum = (string)($data['checksum'] ?? ($data['sig'] ?? ''));
        $memo = (array)($data['memo'] ?? []);

        $payload = new static($id, $component, $state, $checksum, $memo);

        if ($verify) {
            $payload->verify($secret);
        }

        return $payload;
    }

    public static function fromEncoded(string $encodedString, ?string $secret = null, bool $verify = true): static
    {
        $decoded = base64_decode($encodedString, true);
        if ($decoded === false) {
            // Check if it was raw json
            if (json_validate($encodedString)) {
                $decoded = $encodedString;
            } else {
                throw new InvalidSignatureException("Malformed LiveDOM payload: base64 decode failed.");
            }
        }

        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            throw new InvalidSignatureException("Malformed LiveDOM payload: JSON decode failed.");
        }

        return self::fromArray($data, $secret, $verify);
    }

    public function verify(?string $secret = null): bool
    {
        $expected = self::generateChecksum($this->id, $this->state, $secret);
        if (!hash_equals($expected, $this->checksum)) {
            throw new InvalidSignatureException(
                "LiveDOM state signature verification failed for component [{$this->component}]. Property tampering detected."
            );
        }

        return true;
    }

    public function encode(): string
    {
        $json = json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return base64_encode((string)$json);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function getState(): array
    {
        return $this->state;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getMemo(): array
    {
        return $this->memo;
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'component' => $this->component,
            'state'     => $this->state,
            'checksum'  => $this->checksum,
            'memo'      => $this->memo,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
