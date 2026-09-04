<?php
declare(strict_types=1);

namespace Tests\Unit\Cli;

use Oshim\Testing\TestCase;
use Oshim\Cli\Commands\SelfUpdateCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;

class SelfUpdateCommandTest extends TestCase
{
    public function testSelfUpdateExecution(): void
    {
        $cmd = new SelfUpdateCommand();
        $input = new Input(['bin/oshim', 'self:update']);
        $output = new Output();

        $exitCode = $cmd->execute($input, $output);
        $this->assertSame(0, $exitCode);
    }
}
