<?php
declare(strict_types=1);

namespace Oshim\App;

class AppManifest
{
    private string $id;
    private string $name;
    private string $version;
    private string $type; // web, mobile, desktop, api, ai, fullstack
    private array $targets; // android, ios, windows, mac, linux, web
    private array $config;

    public function __construct(
        string $name,
        string $type = 'fullstack',
        string $version = '1.0.0',
        array $targets = ['web', 'android', 'ios', 'windows', 'mac', 'linux'],
        array $config = []
    ) {
        $this->name = $name;
        $this->id = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '.', $name) ?: 'oshim.app');
        $this->type = $type;
        $this->version = $version;
        $this->targets = $targets;
        $this->config = $config;
    }

    public static function make(string $name, string $type = 'fullstack'): self
    {
        return new self($name, $type);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getTargets(): array
    {
        return $this->targets;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'version' => $this->version,
            'targets' => $this->targets,
            'config' => $this->config,
            'runtime' => 'OSHIM Sovereign Universal Engine v1.0.0',
            'dsl_mode' => 'Pure PHP 8.3+ Zero-Dependency',
        ];
    }
}
