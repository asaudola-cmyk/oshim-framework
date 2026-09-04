<?php
declare(strict_types=1);

namespace Oshim\Virtualization\CloudInit;

/**
 * Cloud-Init Instance Provisioning & Metadata Service.
 */
class CloudInitService
{
    /**
     * Generate `user-data` cloud-config YAML.
     */
    public static function generateUserData(
        string $hostname,
        string $username = 'oshim',
        ?string $sshAuthorizedKey = null,
        array $packages = ['curl', 'htop', 'git'],
        array $runCommands = []
    ): string {
        $yaml = "#cloud-config\n";
        $yaml .= "hostname: {$hostname}\n";
        $yaml .= "manage_etc_hosts: true\n\n";

        $yaml .= "users:\n";
        $yaml .= "  - name: {$username}\n";
        $yaml .= "    sudo: ALL=(ALL) NOPASSWD:ALL\n";
        $yaml .= "    shell: /bin/bash\n";

        if ($sshAuthorizedKey !== null && trim($sshAuthorizedKey) !== '') {
            $yaml .= "    ssh_authorized_keys:\n";
            $yaml .= "      - " . trim($sshAuthorizedKey) . "\n";
        }

        if (!empty($packages)) {
            $yaml .= "\npackages:\n";
            foreach ($packages as $pkg) {
                $yaml .= "  - {$pkg}\n";
            }
        }

        if (!empty($runCommands)) {
            $yaml .= "\nruncmd:\n";
            foreach ($runCommands as $cmd) {
                $yaml .= "  - '{$cmd}'\n";
            }
        }

        return $yaml;
    }

    /**
     * Generate `meta-data` JSON / YAML.
     */
    public static function generateMetaData(string $instanceId, string $hostname, ?string $publicIpv4 = null): array
    {
        return [
            'instance-id' => $instanceId,
            'local-hostname' => $hostname,
            'public-ipv4' => $publicIpv4 ?? '10.42.0.10',
            'availability-zone' => 'oshim-dhaka-dc1',
            'created-at' => date('c'),
        ];
    }

    /**
     * Generate `vendor-data` for sovereign cloud defaults.
     */
    public static function generateVendorData(): string
    {
        return "#cloud-config\noshim_cloud_managed: true\ntelemetry_enabled: true\n";
    }
}
