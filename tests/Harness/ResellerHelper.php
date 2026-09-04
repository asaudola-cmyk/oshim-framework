<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use RuntimeException;

class ResellerHelper
{
    private array $branding = [];
    private array $quotas = [];

    public function setBranding(int $resellerId, array $branding): void
    {
        $this->branding[$resellerId] = $branding;
    }

    public function getBranding(int $resellerId): ?array
    {
        return $this->branding[$resellerId] ?? null;
    }

    public function setQuota(int $resellerId, array $quota): void
    {
        $this->quotas[$resellerId] = array_merge([
            'max_vcpu' => 32,
            'max_ram_mb' => 65536,
            'max_disk_gb' => 2000,
            'used_vcpu' => 0,
            'used_ram_mb' => 0,
            'used_disk_gb' => 0,
        ], $quota);
    }

    public function getQuota(int $resellerId): array
    {
        return $this->quotas[$resellerId] ?? [
            'max_vcpu' => 0, 'max_ram_mb' => 0, 'max_disk_gb' => 0,
            'used_vcpu' => 0, 'used_ram_mb' => 0, 'used_disk_gb' => 0,
        ];
    }

    public function provisionSubClientInstance(int $resellerId, int $subClientId, array $spec): array
    {
        $quota = $this->getQuota($resellerId);
        $reqVcpu = $spec['vcpu'] ?? 1;
        $reqRam = $spec['ram_mb'] ?? 1024;
        $reqDisk = $spec['disk_gb'] ?? 20;

        if (($quota['used_vcpu'] + $reqVcpu) > $quota['max_vcpu']) {
            throw new RuntimeException("Reseller vCPU quota exceeded.");
        }
        if (($quota['used_ram_mb'] + $reqRam) > $quota['max_ram_mb']) {
            throw new RuntimeException("Reseller RAM quota exceeded.");
        }
        if (($quota['used_disk_gb'] + $reqDisk) > $quota['max_disk_gb']) {
            throw new RuntimeException("Reseller Disk quota exceeded.");
        }

        $this->quotas[$resellerId]['used_vcpu'] += $reqVcpu;
        $this->quotas[$resellerId]['used_ram_mb'] += $reqRam;
        $this->quotas[$resellerId]['used_disk_gb'] += $reqDisk;

        $instId = 'sub-vm-' . bin2hex(random_bytes(4));
        return [
            'instance_id' => $instId,
            'reseller_id' => $resellerId,
            'sub_client_id' => $subClientId,
            'spec' => $spec,
            'status' => 'PROVISIONED',
        ];
    }
}
