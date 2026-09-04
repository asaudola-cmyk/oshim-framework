<?php
declare(strict_types=1);

namespace Oshim\Ai\Agents;

use Oshim\Ai\Tools\AiAgent;
use Oshim\Ai\Tools\ToolRegistry;

/**
 * Delegated Agent Task Specification.
 */
class AgentTask
{
    public string $description;
    public string $expectedOutput;
    public ?string $assignedRole;
    public ?string $output = null;

    public function __construct(string $description, string $expectedOutput = '', ?string $assignedRole = null)
    {
        $this->description = $description;
        $this->expectedOutput = $expectedOutput;
        $this->assignedRole = $assignedRole;
    }
}
