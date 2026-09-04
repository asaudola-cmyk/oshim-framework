<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Ai\Agents\AgentTeam;
use Oshim\Ai\Agents\AgentTask;

class AiTeamCommand extends Command
{
    protected string $name = 'ai:team';
    protected string $description = 'Execute task with an autonomous multi-agent squad (CrewAI style)';

    protected function configure(): void
    {
        $this->addArgument('task', Input::OPTIONAL, 'The task for the multi-agent team to solve', 'Design and deploy MicroVM Cloud Cluster');
    }

    public function execute(Input $input, Output $output): int
    {
        $taskDesc = (string)($input->getArgument('task') ?? $input->getArgument(0) ?? 'Design and deploy MicroVM Cloud Cluster');

        $output->writeln("\n<bold><cyan>👑 OSHIM Sovereign Multi-Agent Squad</cyan></bold>");
        $output->writeln("Squad: <comment>Architecture & Engineering Crew</comment>");
        $output->writeln("Task: <green>{$taskDesc}</green>\n");

        $team = AgentTeam::squad('DevOps & Cloud Squad')
            ->addMember('System Architect', 'Design high throughput distributed systems')
            ->addMember('Cloud Engineer', 'Provision KVM MicroVM and configure Cgroups')
            ->addMember('Security Auditor', 'Perform zero-trust verification and TOTP');

        $team->addTask(new AgentTask($taskDesc, 'Structured blueprint', 'System Architect'))
             ->addTask(new AgentTask("Deploy and verify infrastructure for: {$taskDesc}", 'Deployment report', 'Cloud Engineer'));

        $output->writeln("<info>⚡ Dispatching agents...</info>");
        $result = $team->kickoff(['initial_request' => $taskDesc]);

        $output->writeln("\n<info>✔ Squad Completed {$result['tasks_completed']} Tasks:</info>");
        foreach ($result['results'] as $r) {
            $output->writeln("  [<comment>{$r['role']}</comment>] Task: {$r['task']}");
            $output->writeln("    -> " . substr($r['output'], 0, 120) . "...\n");
        }

        return 0;
    }
}
