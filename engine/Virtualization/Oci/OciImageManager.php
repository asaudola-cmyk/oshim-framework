<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Oci;

use RuntimeException;
use InvalidArgumentException;

/**
 * OCI (Open Container Initiative) & Docker Layer Manager.
 */
class OciImageManager
{
    private string $storagePath;
    private array $cachedManifests = [];

    public function __construct(string $storagePath = '/var/lib/oshim/oci')
    {
        $this->storagePath = rtrim($storagePath, '/\\');
    }

    /**
     * Parse and validate an OCI Image Manifest JSON.
     */
    public static function parseManifest(string $manifestJson): array
    {
        $manifest = json_decode($manifestJson, true);
        if (!is_array($manifest)) {
            throw new InvalidArgumentException("Invalid OCI manifest JSON.");
        }

        $schemaVersion = $manifest['schemaVersion'] ?? 2;
        $mediaType = $manifest['mediaType'] ?? 'application/vnd.oci.image.manifest.v1+json';
        $config = $manifest['config'] ?? [];
        $layers = $manifest['layers'] ?? [];

        return [
            'schema_version' => $schemaVersion,
            'media_type' => $mediaType,
            'config_digest' => $config['digest'] ?? '',
            'config_size' => $config['size'] ?? 0,
            'layer_count' => count($layers),
            'layers' => $layers,
        ];
    }

    /**
     * Build an OverlayFS lowerdir chain from OCI layers.
     */
    public static function computeOverlayLowerDir(array $layers, string $baseLayersPath): string
    {
        $paths = [];
        // OCI layers in manifest are ordered from base (index 0) to top layer
        // OverlayFS expects highest precedence on the left: top:layer2:layer1:base
        $reversed = array_reverse($layers);
        foreach ($reversed as $layer) {
            $digest = $layer['digest'] ?? '';
            $sanitized = str_replace('sha256:', '', $digest);
            if ($sanitized !== '') {
                $paths[] = "{$baseLayersPath}/{$sanitized}/diff";
            }
        }

        return implode(':', $paths);
    }

    /**
     * Register a local rootfs image definition.
     */
    public function registerImage(string $tag, array $layers, array $env = [], string $entrypoint = '/bin/sh'): array
    {
        $image = [
            'tag' => $tag,
            'layers' => $layers,
            'env' => $env,
            'entrypoint' => $entrypoint,
            'created_at' => time(),
        ];

        $this->cachedManifests[$tag] = $image;
        return $image;
    }

    public function getImage(string $tag): ?array
    {
        return $this->cachedManifests[$tag] ?? null;
    }
}
