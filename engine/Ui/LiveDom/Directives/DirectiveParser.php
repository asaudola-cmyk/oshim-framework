<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom\Directives;

/**
 * Parses declarative live:* and data-live-* directives and expressions.
 */
class DirectiveParser
{
    /**
     * Parse an attribute name into its directive type, event, and modifiers.
     * Example: "live:click.prevent.stop" -> type: "click", modifiers: ["prevent", "stop"]
     * Example: "live:input.debounce.300ms" -> type: "input", debounce: 300, modifiers: ["debounce"]
     *
     * @return array{
     *     type: string,
     *     event: ?string,
     *     modifiers: array<string>,
     *     debounce: ?int,
     *     throttle: ?int,
     *     is_model: bool,
     *     is_poll: bool,
     *     is_loading: bool
     * }
     */
    public static function parseAttribute(string $name): ?array
    {
        $name = trim($name);
        if (!str_starts_with($name, 'live:') && !str_starts_with($name, 'data-live-')) {
            return null;
        }

        $raw = str_starts_with($name, 'live:') ? substr($name, 5) : substr($name, 10);
        $parts = explode('.', $raw);
        $directive = strtolower(array_shift($parts) ?? '');

        $modifiers = [];
        $debounce = null;
        $throttle = null;

        for ($i = 0; $i < count($parts); $i++) {
            $part = strtolower($parts[$i]);
            if ($part === 'debounce') {
                $next = $parts[$i + 1] ?? null;
                if ($next !== null && preg_match('/^(\d+)(ms|s)?$/', $next, $m)) {
                    $ms = (int)$m[1];
                    if (($m[2] ?? '') === 's') {
                        $ms *= 1000;
                    }
                    $debounce = $ms;
                    $i++; // skip next
                } else {
                    $debounce = 250; // default debounce
                }
                $modifiers[] = 'debounce';
            } elseif ($part === 'throttle') {
                $next = $parts[$i + 1] ?? null;
                if ($next !== null && preg_match('/^(\d+)(ms|s)?$/', $next, $m)) {
                    $ms = (int)$m[1];
                    if (($m[2] ?? '') === 's') {
                        $ms *= 1000;
                    }
                    $throttle = $ms;
                    $i++;
                } else {
                    $throttle = 250;
                }
                $modifiers[] = 'throttle';
            } else {
                // Check if modifier itself has duration like "poll.2000ms" or "poll.5s"
                if (preg_match('/^(\d+)(ms|s)$/', $part, $m)) {
                    $ms = (int)$m[1];
                    if ($m[2] === 's') {
                        $ms *= 1000;
                    }
                    if ($directive === 'poll') {
                        $debounce = $ms;
                    }
                } else {
                    $modifiers[] = $part;
                }
            }
        }

        $isModel = $directive === 'model';
        $isPoll = $directive === 'poll';
        $isLoading = str_starts_with($directive, 'loading');

        return [
            'type'       => $directive,
            'event'      => (!$isModel && !$isPoll && !$isLoading) ? $directive : null,
            'modifiers'  => $modifiers,
            'debounce'   => $debounce,
            'throttle'   => $throttle,
            'is_model'   => $isModel,
            'is_poll'    => $isPoll,
            'is_loading' => $isLoading,
        ];
    }

    /**
     * Parse action expression into method name and arguments.
     * Example: "increment" -> action: "increment", args: []
     * Example: "setFilter('active', 10, true)" -> action: "setFilter", args: ["active", 10, true]
     *
     * @return array{
     *     action: string,
     *     args: array<mixed>
     * }
     */
    public static function parseExpression(string $expr): array
    {
        $expr = trim($expr);
        if ($expr === '') {
            return ['action' => '', 'args' => []];
        }

        if (preg_match('/^([a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*)\s*(?:\((.*)\))?$/s', $expr, $matches)) {
            $action = $matches[1];
            $rawArgs = $matches[2] ?? null;

            if ($rawArgs === null || trim($rawArgs) === '') {
                return ['action' => $action, 'args' => []];
            }

            $args = self::parseArguments($rawArgs);
            return ['action' => $action, 'args' => $args];
        }

        return ['action' => $expr, 'args' => []];
    }

    /**
     * Parse comma-separated arguments with literal strings, numbers, booleans, and nulls.
     */
    public static function parseArguments(string $argsString): array
    {
        $argsString = trim($argsString);
        if ($argsString === '') {
            return [];
        }

        // Use json_decode by wrapping in brackets if valid JSON array format
        $jsonCandidate = '[' . $argsString . ']';
        $decoded = json_decode($jsonCandidate, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Fallback: tokenize manually for single quotes or unquoted literals
        $tokens = [];
        $current = '';
        $inQuote = false;
        $quoteChar = '';
        $len = strlen($argsString);

        for ($i = 0; $i < $len; $i++) {
            $ch = $argsString[$i];

            if ($inQuote) {
                if ($ch === $quoteChar && ($i === 0 || $argsString[$i - 1] !== '\\')) {
                    $inQuote = false;
                } else {
                    $current .= $ch;
                }
            } else {
                if ($ch === '\'' || $ch === '"') {
                    $inQuote = true;
                    $quoteChar = $ch;
                } elseif ($ch === ',') {
                    $tokens[] = self::castLiteral(trim($current));
                    $current = '';
                } else {
                    $current .= $ch;
                }
            }
        }

        if ($current !== '') {
            $tokens[] = self::castLiteral(trim($current));
        }

        return $tokens;
    }

    private static function castLiteral(string $val): mixed
    {
        $lower = strtolower($val);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }
        if (is_numeric($val)) {
            return str_contains($val, '.') ? (float)$val : (int)$val;
        }
        return $val;
    }
}
