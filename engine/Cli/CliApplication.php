<?php
declare(strict_types=1);

namespace Oshim\Cli;

use Oshim\Container\Container;
use Throwable;

class CliApplication
{
    private string $name = 'OSHIM Cloud CLI';
    private string $version = '1.0.0';
    /** @var array<string, Command> */
    private array $commands = [];
    private Container $container;

    public function __construct(?Container $container = null)
    {
        $this->container = $container ?? Container::getInstance();
    }

    public function register(Command $command): self
    {
        $this->commands[$command->getName()] = $command;
        return $this;
    }

    public function registerClass(string $commandClass): self
    {
        $command = $this->container->make($commandClass);
        if ($command instanceof Command) {
            $this->register($command);
        }
        return $this;
    }

    public function get(string $name): ?Command
    {
        return $this->commands[$name] ?? null;
    }

    public function all(): array
    {
        return $this->commands;
    }

    public function run(array $argv): int
    {
        $input = new Input($argv);
        $output = new Output();

        $cmdName = $input->getCommandName();

        if ($cmdName === null) {
            if ($input->hasOption('version') || $input->hasOption('v') || $input->hasOption('V')) {
                $output->writeln("<cyan>{$this->name}</cyan> version <green>{$this->version}</green>");
                return 0;
            }
            $this->renderHelp($output);
            return 0;
        }

        if ($cmdName === 'list' || $input->hasOption('help') || $input->hasOption('h')) {
            $this->renderHelp($output);
            return 0;
        }

        if ($input->hasOption('version') || $input->hasOption('V')) {
            $output->writeln("<cyan>{$this->name}</cyan> version <green>{$this->version}</green>");
            return 0;
        }

        $command = $this->get($cmdName);

        if ($command === null) {
            $output->error("Command '{$cmdName}' is not defined.");
            $output->writeln();
            $this->renderHelp($output);
            return 1;
        }

        try {
            return $command->execute($input, $output);
        } catch (Throwable $e) {
            $output->error("Execution error: " . $e->getMessage());
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                $output->writeln("<dim>" . $e->getTraceAsString() . "</dim>");
            }
            return 1;
        }
    }

    public function renderHelp(Output $output): void
    {
        $output->writeln("<bold><cyan>{$this->name}</cyan></bold> <green>{$this->version}</green>");
        $output->writeln();
        $output->writeln("<yellow>Usage:</yellow>");
        $output->writeln("  command [options] [arguments]");
        $output->writeln();
        $output->writeln("<yellow>Options:</yellow>");
        $output->writeln("  <green>-h, --help</green>     Display help for the given command");
        $output->writeln("  <green>-V, --version</green>  Display this application version");
        $output->writeln("  <green>-v, --verbose</green>  Increase the verbosity of messages");
        $output->writeln();
        $output->writeln("<yellow>Available Commands:</yellow>");

        $grouped = [];
        foreach ($this->commands as $name => $cmd) {
            $namespace = str_contains($name, ':') ? explode(':', $name)[0] : 'general';
            $grouped[$namespace][$name] = $cmd;
        }

        ksort($grouped);

        foreach ($grouped as $ns => $cmds) {
            if ($ns !== 'general') {
                $output->writeln(" <bold>{$ns}</bold>");
            }
            foreach ($cmds as $name => $cmd) {
                $padding = str_repeat(' ', max(2, 24 - mb_strlen($name)));
                $output->writeln("  <green>{$name}</green>{$padding}{$cmd->getDescription()}");
            }
        }
    }
}
