<?php
declare(strict_types=1);

namespace Oshim\Tenant;

class Tenant
{
    public string $id;
    public string $name;
    public string $subdomain;
    public ?string $customDomain;
    public array $config;

    public function __construct(string $id, string $name, string $subdomain, ?string $customDomain = null, array $config = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->subdomain = $subdomain;
        $this->customDomain = $customDomain;
        $this->config = $config;
    }

    public function getDatabaseName(): string
    {
        return $this->config['database'] ?? ('tenant_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $this->id));
    }
}
