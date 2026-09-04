<?php
declare(strict_types=1);

namespace Oshim\Http\Cookie;

use Oshim\Security\Cipher;

class Cookie
{
    public function __construct(
        protected string $name,
        protected ?string $value = null,
        protected int $expire = 0,
        protected string $path = '/',
        protected string $domain = '',
        protected bool $secure = false,
        protected bool $httpOnly = true,
        protected string $sameSite = 'Lax',
        protected bool $encrypted = false
    ) {}

    public static function make(
        string $name,
        string $value,
        int $minutes = 0,
        string $path = '/',
        string $domain = '',
        ?bool $secure = null,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        $expire = $minutes === 0 ? 0 : time() + ($minutes * 60);
        $secure = $secure ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        return new static(
            name: $name,
            value: $value,
            expire: $expire,
            path: $path,
            domain: $domain,
            secure: $secure,
            httpOnly: $httpOnly,
            sameSite: $sameSite
        );
    }

    public static function forever(string $name, string $value, string $path = '/', string $domain = ''): static
    {
        return static::make($name, $value, 525600 * 5, $path, $domain); // 5 years
    }

    public static function forget(string $name, string $path = '/', string $domain = ''): static
    {
        return new static(
            name: $name,
            value: '',
            expire: time() - 86400,
            path: $path,
            domain: $domain
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getExpire(): int
    {
        return $this->expire;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    public function isEncrypted(): bool
    {
        return $this->encrypted;
    }

    /**
     * Encrypt cookie value with application key using AES-256-GCM.
     */
    public function encrypt(string $appKey): static
    {
        if ($this->value === null || $this->value === '') {
            return $this;
        }

        $clone = clone $this;
        $clone->value = Cipher::encrypt($this->value, $appKey, $this->name);
        $clone->encrypted = true;
        return $clone;
    }

    /**
     * Decrypt cookie value with application key.
     */
    public function decrypt(string $appKey): ?string
    {
        if ($this->value === null || $this->value === '') {
            return $this->value;
        }

        try {
            return Cipher::decrypt($this->value, $appKey, $this->name);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Formats cookie as RFC 6265 Set-Cookie header string.
     */
    public function toHeaderString(): string
    {
        $cookie = rawurlencode($this->name) . '=' . rawurlencode($this->value ?? '');

        if ($this->expire !== 0) {
            $cookie .= '; Expires=' . gmdate('D, d M Y H:i:s T', $this->expire);
            $cookie .= '; Max-Age=' . max(0, $this->expire - time());
        }

        if ($this->path !== '') {
            $cookie .= '; Path=' . $this->path;
        }

        if ($this->domain !== '') {
            $cookie .= '; Domain=' . $this->domain;
        }

        if ($this->secure) {
            $cookie .= '; Secure';
        }

        if ($this->httpOnly) {
            $cookie .= '; HttpOnly';
        }

        if ($this->sameSite !== '') {
            $cookie .= '; SameSite=' . ucfirst(strtolower($this->sameSite));
        }

        return $cookie;
    }

    public function __toString(): string
    {
        return $this->toHeaderString();
    }
}
