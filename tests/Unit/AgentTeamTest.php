<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ai\Agents\AgentTeam;
use Oshim\Ai\Agents\AgentTask;

final class AgentTeamTest extends TestCase
{
    public function testAgentTeamKickoff(): void
    {
        $team = AgentTeam::squad('DevOps Crew')
            ->addMember('Architect', 'Design secure systems')
            ->addMember('Coder', 'Implement code according to design');

        $team->addTask(new AgentTask('Design a high RPS architecture', 'Blueprint', 'Architect'))
             ->addTask(new AgentTask('Implement zero-dependency router', 'PHP code', 'Coder'));

        $res = $team->kickoff(['project' => 'OSHIM Core']);

        $this->assertSame('SUCCESS', $res['status']);
        $this->assertSame(2, $res['tasks_completed']);
        $this->assertCount(2, $res['results']);
    }
}
