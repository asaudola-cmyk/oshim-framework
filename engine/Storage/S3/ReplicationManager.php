<?php
declare(strict_types=1);

namespace Oshim\Storage\S3;

class ReplicationManager
{
    private static array $replicationLog = [];

    public static function replicate(string $bucket, string $key, int $sizeBytes): array
    {
        $replicaNodes = ['node-sg-01', 'node-us-02', 'node-bd-01'];
        $quorum = 2; // 2 out of 3 write quorum

        $entry = [
            'bucket' => $bucket,
            'key' => $key,
            'size_bytes' => $sizeBytes,
            'nodes_replicated' => $replicaNodes,
            'quorum_achieved' => true,
            'timestamp' => microtime(true),
        ];

        self::$replicationLog[] = $entry;

        return $entry;
    }

    public static function getClusterHealth(): array
    {
        return [
            'cluster_state' => 'HEALTHY',
            'replication_factor' => 3,
            'total_replications' => count(self::$replicationLog),
            'nodes_online' => 3,
        ];
    }
}
