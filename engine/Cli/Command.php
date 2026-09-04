<?php
declare(strict_types=1);

namespace Oshim\Cli;

abstract class Command
{
    protected string $name = '';
    protected string $description = '';
    protected string $help = '';
    protected array $arguments = [];
    protected array $options = [];

    public function __construct()
    {
        $this->configure();
    }

    protected function configure(): void
    {
    }

    abstract public function execute(Input $input, Output $output): int;

    public function addArgument(string $name, int $mode = Input::OPTIONAL, string $description = '', mixed $default = null): static
    {
        $this->arguments[$name] = [
            'name'        => $name,
            'mode'        => $mode,
            'description' => $description,
            'default'     => $default,
        ];
        return $this;
    }

    public function addOption(string $name, ?string $shortcut = null, int $mode = Input::VALUE_NONE, string $description = '', mixed $default = null): static
    {
        $this->options[$name] = [
            'name'        => $name,
            'shortcut'    => $shortcut,
            'mode'        => $mode,
            'description' => $description,
            'default'     => $default,
        ];
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getHelp(): string
    {
        return $this->help;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
