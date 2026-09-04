<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ai\Schema\Type;
use Oshim\Ai\Schema\StructuredOutputExtractor;

final class StructuredOutputTest extends TestCase
{
    public function testTypeSchemaValidation(): void
    {
        $schema = Type::object([
            'server_id' => Type::string('Unique ID'),
            'cores' => Type::int('CPU cores'),
            'memory_mb' => Type::int('Memory in MB'),
            'status' => Type::enum(['running', 'stopped', 'paused']),
        ], ['server_id', 'cores', 'memory_mb', 'status']);

        $validData = [
            'server_id' => 'vm-node-01',
            'cores' => 4,
            'memory_mb' => 8192,
            'status' => 'running',
        ];

        $this->assertTrue($schema->validate($validData));

        $invalidData = [
            'server_id' => 'vm-node-01',
            'cores' => 'four', // not int
            'memory_mb' => 8192,
            'status' => 'unknown_status', // invalid enum
        ];

        $this->assertFalse($schema->validate($invalidData));
    }
}
