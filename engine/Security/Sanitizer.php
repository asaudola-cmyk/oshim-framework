<?php
declare(strict_types=1);

namespace Oshim\Security;

final class Sanitizer
{
    public static function escapeHtml(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function stripTags(string $input, ?string $allowedTags = null): string
    {
        return strip_tags($input, $allowedTags);
    }

    public static function cleanString(string $input): string
    {
        // Strip null bytes and non-printable control characters except newline and tab
        $clean = str_replace("\0", '', $input);
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean) ?? '';
    }

    public static function cleanArray(array $input): array
    {
        $cleaned = [];
        foreach ($input as $key => $value) {
            $cleanKey = is_string($key) ? self::cleanString($key) : $key;
            if (is_array($value)) {
                $cleaned[$cleanKey] = self::cleanArray($value);
            } elseif (is_string($value)) {
                $cleaned[$cleanKey] = self::cleanString($value);
            } else {
                $cleaned[$cleanKey] = $value;
            }
        }
        return $cleaned;
    }

    public static function slug(string $title, string $separator = '-'): string
    {
        $slug = mb_strtolower($title, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9_\s-]/u', '', $slug) ?? '';
        $slug = preg_replace('/[\s_-]+/', $separator, $slug) ?? '';
        return trim($slug, $separator);
    }
}
