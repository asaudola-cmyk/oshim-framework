<?php
declare(strict_types=1);

namespace Tests\Unit\GraphQL;

use Oshim\Testing\TestCase;
use Oshim\GraphQL\GraphQLServer;

class GraphQLServerTest extends TestCase
{
    public function testGraphQLQueryExecution(): void
    {
        $gql = new GraphQLServer();
        $gql->query('hello', fn($args) => "Hello " . ($args['name'] ?? 'World'));
        $gql->query('serverInfo', fn() => [
            'os' => 'Linux',
            'uptime' => '99.99%',
            'cores' => 32,
        ]);

        $res = $gql->execute('{ hello(name: "Sovereign") }');
        $this->assertArrayHasKey('data', $res);
        $this->assertSame('Hello Sovereign', $res['data']['hello']);

        $resSub = $gql->execute('{ serverInfo { os uptime } }');
        $this->assertArrayHasKey('serverInfo', $resSub['data']);
        $this->assertSame('Linux', $resSub['data']['serverInfo']['os']);
        $this->assertSame('99.99%', $resSub['data']['serverInfo']['uptime']);
        $this->assertFalse(isset($resSub['data']['serverInfo']['cores']));
    }

    public function testGraphQLMutationExecution(): void
    {
        $gql = new GraphQLServer();
        $gql->mutation('spawnMicroVm', function($args) {
            return [
                'vmId' => 'vm-' . bin2hex(random_bytes(4)),
                'status' => 'RUNNING',
            ];
        });

        $res = $gql->execute('mutation { spawnMicroVm { vmId status } }');
        $this->assertArrayHasKey('data', $res);
        $this->assertArrayHasKey('spawnMicroVm', $res['data']);
        $this->assertSame('RUNNING', $res['data']['spawnMicroVm']['status']);
    }
}
