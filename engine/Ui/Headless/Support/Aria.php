<?php
declare(strict_types=1);

namespace Oshim\Ui\Headless\Support;

/**
 * WAI-ARIA Attribute Helper for Headless UI Primitives.
 * Provides standard ARIA roles, states, properties, and attribute string generation.
 */
final class Aria
{
    // Common ARIA Roles
    public const ROLE_DIALOG = 'dialog';
    public const ROLE_ALERTDIALOG = 'alertdialog';
    public const ROLE_MENU = 'menu';
    public const ROLE_MENUBAR = 'menubar';
    public const ROLE_MENUITEM = 'menuitem';
    public const ROLE_MENUITEMCHECKBOX = 'menuitemcheckbox';
    public const ROLE_MENUITEMRADIO = 'menuitemradio';
    public const ROLE_SEPARATOR = 'separator';
    public const ROLE_GROUP = 'group';
    public const ROLE_COMBOBOX = 'combobox';
    public const ROLE_LISTBOX = 'listbox';
    public const ROLE_OPTION = 'option';
    public const ROLE_REGION = 'region';
    public const ROLE_HEADING = 'heading';
    public const ROLE_BUTTON = 'button';
    public const ROLE_NONE = 'none';
    public const ROLE_PRESENTATION = 'presentation';

    /**
     * Compiles an associative array of attributes into an escaped HTML attribute string.
     *
     * @param array<string, mixed> $attributes
     */
    public static function compile(array $attributes): string
    {
        if (empty($attributes)) {
            return '';
        }

        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $escapedKey = htmlspecialchars((string)$key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            if ($value === true) {
                // For aria-* and data-* attributes, true is represented as "true"
                if (str_starts_with($key, 'aria-') || str_starts_with($key, 'data-')) {
                    $parts[] = "{$escapedKey}=\"true\"";
                } else {
                    $parts[] = $escapedKey;
                }
                continue;
            }

            if (is_array($value)) {
                $valStr = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $valStr = (string)$value;
            }

            $escapedValue = htmlspecialchars($valStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $parts[] = "{$escapedKey}=\"{$escapedValue}\"";
        }

        return !empty($parts) ? ' ' . implode(' ', $parts) : '';
    }

    /**
     * Converts a boolean state to standard ARIA string ('true' or 'false').
     */
    public static function boolString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Returns state attribute array for open/closed states.
     *
     * @return array<string, string>
     */
    public static function state(bool $isOpen): array
    {
        return [
            'data-state' => $isOpen ? 'open' : 'closed',
        ];
    }
}
