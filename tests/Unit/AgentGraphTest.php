<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ai\Agents\AgentGraph;
use Oshim\Ai\Agents\AgentState;

final class AgentGraphTest extends TestCase
{
    public function testCyclicStatefulGraphExecution(): void
    {
        $graph = new AgentGraph();

        // Node 1: Planner
        $graph->addNode('planner', function (AgentState $state) {
            $goal = $state->get('goal', '');
            return [
                'plan' => "Plan for {$goal}",
                'attempts' => 1,
            ];
        });

        // Node 2: Executor
        $graph->addNode('executor', function (AgentState $state) {
            $attempts = $state->get('attempts', 0);
            return [
                'result' => 'Executed',
                'attempts' => $attempts + 1,
            ];
        });

        // Node 3: Reviewer
        $graph->addNode('reviewer', function (AgentState $state) {
            $attempts = $state->get('attempts', 0);
            return [
                'verified' => $attempts >= 3,
            ];
        });

        $graph->setEntryPoint('planner');
        $graph->addEdge('planner', 'executor');
        $graph->addEdge('executor', 'reviewer');

        // Conditional edge: If verified -> END, else loop back to executor
        $graph->addConditionalEdge('reviewer', function (AgentState $state) {
            return $state->get('verified') ? AgentGraph::END : 'executor';
        });

        $output = $graph->run(['goal' => 'Launch MicroVM']);

        $this->assertSame('COMPLETED', $output['status']);
        $this->assertTrue($output['state']['verified']);
        $this->assertTrue(in_array('planner', $output['path']));
        $this->assertTrue(in_array('executor', $output['path']));
        $this->assertTrue(in_array('reviewer', $output['path']));
    }
}
