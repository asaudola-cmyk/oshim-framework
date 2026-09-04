<?php
declare(strict_types=1);

namespace Oshim\Security;

use Oshim\Http\Session\Session;

final class Csrf
{
    public const TOKEN_KEY = '_csrf_token';
    public const HEADER_NAME = 'X-CSRF-TOKEN';

    public static function token(?Session $session = null): string
    {
        if ($session !== null) {
            return $session->token();
        }

        if (isset($_SESSION[self::TOKEN_KEY])) {
            return (string)$_SESSION[self::TOKEN_KEY];
        }

        $token = bin2hex(random_bytes(32));
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::TOKEN_KEY] = $token;
        }

        return $token;
    }

    public static function validate(?Session $session, ?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $expected = self::token($session);
        return hash_equals($expected, $token);
    }

    public static function field(?Session $session = null): string
    {
        $token = self::token($session);
        return '<input type="hidden" name="' . self::TOKEN_KEY . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function headerName(): string
    {
        return self::HEADER_NAME;
    }
}
