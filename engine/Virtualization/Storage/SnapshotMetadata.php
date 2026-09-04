<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Storage;

/**
 * Metadata descriptor for an immutable container snapshot.
 */
final class SnapshotMetadata
{
    public function __construct(
        public readonly string $id,
        public readonly string $instanceId,
        public readonly string $name,
        public readonly string $description = '',
        public readonly string $layerPath = '',
        public readonly int $sizeBytes = 0,
        public readonly int $createdAt = 0,
        /** @var list<string> */
        public readonly array $layerStack = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? $data['snapshot_id'] ?? ''),
            instanceId: (string)($data['instance_id'] ?? ''),
            name: (string)($data['name'] ?? $data['snapshot_name'] ?? ''),
            description: (string)($data['description'] ?? ''),
            layerPath: (string)($data['layer_path'] ?? $data['path'] ?? ''),
            sizeBytes: (int)($data['size_bytes'] ?? 0),
            createdAt: (int)($data['created_at'] ?? time()),
            layerStack: (array)($data['layer_stack'] ?? [])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'snapshot_id'   => $this->id,
            'instance_id'   => $this->instanceId,
            'name'          => $this->name,
            'snapshot_name' => $this->name,
            'description'   => $this->description,
            'layer_path'    => $this->layerPath,
            'path'          => $this->layerPath,
            'size_bytes'    => $this->sizeBytes,
            'created_at'    => $this->createdAt,
            'layer_stack'   => $this->layerStack,
        ];
    }
}
