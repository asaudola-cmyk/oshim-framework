<?php
declare(strict_types=1);

namespace Oshim\Cli;

class Terminal
{
    public static function ask(string $question, ?string $default = null): string
    {
        $prompt = $default !== null ? "{$question} [{$default}]: " : "{$question}: ";
        echo $prompt;

        $input = trim((string)fgets(STDIN));
        return $input !== '' ? $input : ($default ?? '');
    }

    public static function confirm(string $question, bool $default = false): bool
    {
        $hint = $default ? 'Y/n' : 'y/N';
        echo "{$question} ({$hint}): ";

        $input = strtolower(trim((string)fgets(STDIN)));
        if ($input === '') {
            return $default;
        }

        return in_array($input, ['y', 'yes', 'true', '1'], true);
    }

    public static function choice(string $question, array $choices, mixed $default = null): string
    {
        echo "{$question}:\n";
        foreach ($choices as $key => $choice) {
            echo "  [{$key}] {$choice}\n";
        }

        $prompt = $default !== null ? "Select option [{$default}]: " : "Select option: ";
        echo $prompt;

        $input = trim((string)fgets(STDIN));
        $selected = $input !== '' ? $input : (string)$default;

        if (array_key_exists($selected, $choices)) {
            return (string)$choices[$selected];
        }

        if (in_array($selected, $choices, true)) {
            return $selected;
        }

        return (string)($choices[0] ?? $selected);
    }

    public static function secret(string $question): string
    {
        echo "{$question}: ";

        if (PHP_OS_FAMILY === 'Windows') {
            return trim((string)fgets(STDIN));
        }

        // Disable terminal echo
        shell_exec('stty -echo');
        $input = trim((string)fgets(STDIN));
        shell_exec('stty echo');
        echo "\n";

        return $input;
    }
}
