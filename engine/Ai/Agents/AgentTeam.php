<?php
declare(strict_types=1);

namespace Oshim\Ai\Agents;

use Oshim\Ai\Tools\AiAgent;
use Oshim\Ai\Tools\ToolRegistry;

/**
 * Autonomous Multi-Agent Collaborative Squad (CrewAI Architecture in Pure PHP 8.3+).
 * Coordinates specialized role-playing agents (Leader, Researcher, Developer, QA Reviewer).
 */
class AgentTeam
{
    /** @var array<string, array{role: string, goal: string, backstory: string, agent: AiAgent}> */
    private array $members = [];
    /** @var array<AgentTask> */
    private array $tasks = [];
    private string $name;

    public function __construct(string $name = 'Sovereign Core Crew')
    {
        $this->name = $name;
    }

    public static function squad(string $name = 'Sovereign Core Crew'): self
    {
        return new self($name);
    }

    public function addMember(string $role, string $goal, string $backstory = '', ?ToolRegistry $tools = null): self
    {
        $agent = new AiAgent($tools ?? new ToolRegistry(), "You are a {$role}. Your goal is: {$goal}. {$backstory}");
        $this->members[$role] = [
            'role' => $role,
            'goal' => $goal,
            'backstory' => $backstory,
            'agent' => $agent,
        ];
        return $this;
    }

    public function addTask(AgentTask $task): self
    {
        $this->tasks[] = $task;
        return $this;
    }

    /**
     * Execute tasks through collaborative multi-agent pipeline.
     */
    public function kickoff(array $inputs = []): array
    {
        $results = [];
        $context = $inputs;

        foreach ($this->tasks as $idx => $task) {
            $assignedRole = $task->assignedRole ?? array_key_first($this->members);
            $member = $this->members[$assignedRole] ?? reset($this->members);

            $prompt = "Role: {$member['role']}\nGoal: {$member['goal']}\nTask: {$task->description}\nContext: " . json_encode($context);
            $output = $member['agent']->run($prompt);

            $task->output = $output['final_response'];
            $results["task_{$idx}_{$assignedRole}"] = [
                'role' => $assignedRole,
                'task' => $task->description,
                'output' => $task->output,
            ];

            // Feed output to next agent in squad
            $context["previous_task_{$idx}"] = $task->output;
        }

        return [
            'team' => $this->name,
            'status' => 'SUCCESS',
            'tasks_completed' => count($this->tasks),
            'results' => $results,
        ];
    }
}
