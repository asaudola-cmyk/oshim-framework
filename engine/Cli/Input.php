<?php
declare(strict_types=1);

namespace Oshim\Cli;

class Input
{
    public const REQUIRED = 1;
    public const OPTIONAL = 2;
    public const VALUE_NONE = 0;
    public const VALUE_REQUIRED = 1;
    public const VALUE_OPTIONAL = 2;

    /** @var array<string, mixed> */
    protected array $arguments = [];
    /** @var array<string, mixed> */
    protected array $options = [];
    /** @var list<string> */
    protected array $rawTokens = [];
    protected ?string $commandName = null;

    public function __construct(array $argv = [])
    {
        // Drop script path
        if (!empty($argv)) {
            array_shift($argv);
        }

        $this->parseTokens($argv);
    }

    protected function parseTokens(array $tokens): void
    {
        $this->rawTokens = $tokens;

        foreach ($tokens as $token) {
            if ($this->commandName === null && !str_starts_with($token, '-')) {
                $this->commandName = $token;
                continue;
            }

            // Long option: --foo=bar or --foo
            if (str_starts_with($token, '--')) {
                $opt = substr($token, 2);
                if (str_contains($opt, '=')) {
                    [$k, $v] = explode('=', $opt, 2);
                    $this->options[$k] = $v;
                } else {
                    $this->options[$opt] = true;
                }
                continue;
            }

            // Short option: -f or -f=bar
            if (str_starts_with($token, '-')) {
                $opt = substr($token, 1);
                if (str_contains($opt, '=')) {
                    [$k, $v] = explode('=', $opt, 2);
                    $this->options[$k] = $v;
                } else {
                    $this->options[$opt] = true;
                }
                continue;
            }

            // Positional argument
            $this->arguments[] = $token;
        }
    }

    public function getCommandName(): ?string
    {
        return $this->commandName;
    }

    public function getArgument(string|int $name, mixed $default = null): mixed
    {
        return $this->arguments[$name] ?? $default;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArgument(string|int $name, mixed $value): void
    {
        $this->arguments[$name] = $value;
    }

    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
