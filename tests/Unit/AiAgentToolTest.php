<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ai\Tools\ToolRegistry;
use Oshim\Ai\Tools\AiAgent;

final class AiAgentToolTest extends TestCase
{
    public function testToolRegistryAndExecution(): void
    {
        $registry = new ToolRegistry();
        $registry->register(
            'calculate_vps_cost',
            'Calculate monthly VPS hosting cost',
            [
                'type' => 'object',
                'properties' => [
                    'cores' => ['type' => 'integer'],
                    'ram_gb' => ['type' => 'integer'],
                ],
                'required' => ['cores', 'ram_gb']
            ],
            function (array $args) {
                $cores = $args['cores'] ?? 2;
                $ram = $args['ram_gb'] ?? 4;
                return ['total_monthly_usd' => ($cores * 5) + ($ram * 2)];
            }
        );

        $this->assertTrue($registry->has('calculate_vps_cost'));
        $result = $registry->execute('calculate_vps_cost', ['cores' => 4, 'ram_gb' => 8]);
        $this->assertSame(36, $result['total_monthly_usd']);

        $schemas = $registry->toSchemaArray();
        $this->assertCount(1, $schemas);
        $this->assertSame('calculate_vps_cost', $schemas[0]['function']['name']);
    }

    public function testAiAgentAutonomousToolExecution(): void
    {
        $registry = new ToolRegistry();
        $called = false;

        $registry->register(
            'get_server_status',
            'Get sovereign server metrics',
            ['type' => 'object', 'properties' => []],
            function () use (&$called) {
                $called = true;
                return ['status' => 'HEALTHY', 'cpu_load' => 0.12];
            }
        );

        $agent = new AiAgent($registry);
        $response = $agent->run("Please get_server_status for our cloud cluster.");

        $this->assertTrue($called);
        $this->assertCount(1, $response['tool_calls']);
        $this->assertSame('get_server_status', $response['tool_calls'][0]['tool']);
        $this->assertNotEmpty($response['final_response']);
    }
}
