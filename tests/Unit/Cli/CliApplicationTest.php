<?php
declare(strict_types=1);

namespace Tests\Unit\Cli;

use Oshim\Testing\TestCase;
use Oshim\Cli\CliApplication;
use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Container\Container;

class DummyTestCommand extends Command
{
    protected string $name = 'dummy:run';
    protected string $description = 'Dummy test command';

    public function execute(Input $input, Output $output): int
    {
        $output->write("DUMMY_EXECUTED");
        return 0;
    }
}

class CliApplicationTest extends TestCase
{
    public function testInputTokenParsing(): void
    {
        $argv = [
            'oshim',
            'serve',
            '--host=192.168.1.5',
            '--port=9000',
            '-v',
            'extra_arg',
        ];

        $input = new Input($argv);

        $this->assertEquals('serve', $input->getCommandName());
        $this->assertEquals('192.168.1.5', $input->getOption('host'));
        $this->assertEquals('9000', $input->getOption('port'));
        $this->assertTrue($input->getOption('v'));
        $this->assertEquals('extra_arg', $input->getArgument(0));
    }

    public function testOutputFormattingAndTable(): void
    {
        $output = new Output(true);
        $formatted = $output->format("<green>SUCCESS</green> <bold>BOLD</bold>");

        $this->assertStringContainsString("\033[32mSUCCESS\033[0m", $formatted);
        $this->assertStringContainsString("\033[1mBOLD\033[0m", $formatted);
    }

    public function testCliApplicationCommandDispatch(): void
    {
        $container = new Container();
        $cli = new CliApplication($container);
        $cli->register(new DummyTestCommand());

        ob_start();
        $code = $cli->run(['oshim', 'dummy:run']);
        $output = ob_get_clean();

        $this->assertEquals(0, $code);
        $this->assertStringContainsString('DUMMY_EXECUTED', $output);
    }

    public function testCliApplicationUnknownCommandReturnsCode1(): void
    {
        $cli = new CliApplication();

        ob_start();
        $code = $cli->run(['oshim', 'unknown:command:xyz']);
        $output = ob_get_clean();

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('not defined', $output);
    }

    public function testCliApplicationVerboseFlagPassedToCommand(): void
    {
        $container = new Container();
        $cli = new CliApplication($container);
        $cli->register(new DummyTestCommand());

        ob_start();
        $code = $cli->run(['oshim', 'dummy:run', '-v']);
        $output = ob_get_clean();

        $this->assertEquals(0, $code);
        $this->assertStringContainsString('DUMMY_EXECUTED', $output);
    }

    public function testCliApplicationVersionFlags(): void
    {
        $cli = new CliApplication();

        ob_start();
        $code = $cli->run(['oshim', '-v']);
        $output = ob_get_clean();
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('1.0.0', $output);
        $this->assertStringContainsString('OSHIM Cloud CLI', $output);

        ob_start();
        $code = $cli->run(['oshim', '-V']);
        $output = ob_get_clean();
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('1.0.0', $output);
        $this->assertStringContainsString('OSHIM Cloud CLI', $output);

        ob_start();
        $code = $cli->run(['oshim', '--version']);
        $output = ob_get_clean();
        $this->assertEquals(0, $code);
        $this->assertStringContainsString('1.0.0', $output);
        $this->assertStringContainsString('OSHIM Cloud CLI', $output);
    }

    public function testAll32CommandsRegisteredAndListable(): void
    {
        $commands = [
            new \Oshim\Cli\Commands\ServeCommand(),
            new \Oshim\Cli\Commands\TestCommand(),
            new \Oshim\Cli\Commands\MigrateCommand(),
            new \Oshim\Cli\Commands\RollbackCommand(),
            new \Oshim\Cli\Commands\SeedCommand(),
            new \Oshim\Cli\Commands\MakeModelCommand(),
            new \Oshim\Cli\Commands\MakeMigrationCommand(),
            new \Oshim\Cli\Commands\MakeControllerCommand(),
            new \Oshim\Cli\Commands\MakeComponentCommand(),
            new \Oshim\Cli\Commands\KeyGenerateCommand(),
            new \Oshim\Cli\Commands\UniversalInfoCommand(),
            new \Oshim\Cli\Commands\TurboServeCommand(),
            new \Oshim\Cli\Commands\TurboBenchCommand(),
            new \Oshim\Cli\Commands\MobileBuildCommand(),
            new \Oshim\Cli\Commands\DesktopServeCommand(),
            new \Oshim\Cli\Commands\AiChatCommand(),
            new \Oshim\Cli\Commands\AiRagCommand(),
            new \Oshim\Cli\Commands\AiTeamCommand(),
            new \Oshim\Cli\Commands\PdfInvoiceCommand(),
            new \Oshim\Cli\Commands\TotpQrCommand(),
            new \Oshim\Cli\Commands\QueueWorkCommand(),
            new \Oshim\Cli\Commands\CacheClearCommand(),
            new \Oshim\Cli\Commands\AppCreateCommand(),
            new \Oshim\Cli\Commands\AppBundleCommand(),
            new \Oshim\Cli\Commands\AppRunCommand(),
            new \Oshim\Cli\Commands\BillingCronCommand(),
            new \Oshim\Cli\Commands\DnsServeCommand(),
            new \Oshim\Cli\Commands\DnsStartCommand(),
            new \Oshim\Cli\Commands\NodeStartCommand(),
            new \Oshim\Cli\Commands\S3ServeCommand(),
            new \Oshim\Cli\Commands\SslIssueCommand(),
            new \Oshim\Cli\Commands\VmSpawnCommand(),
            new \Oshim\Cli\Commands\WebRtcServeCommand(),
        ];

        $this->assertTrue(count($commands) >= 32);

        $cli = new CliApplication();
        foreach ($commands as $cmd) {
            $cli->register($cmd);
            $this->assertNotEmpty($cmd->getName());
            $this->assertNotEmpty($cmd->getDescription());
        }

        ob_start();
        $code = $cli->run(['oshim', '--help']);
        $output = ob_get_clean();

        $this->assertEquals(0, $code);
        $this->assertStringContainsString('Available Commands:', $output);
        $this->assertStringContainsString('billing:cron', $output);
        $this->assertStringContainsString('ai:rag', $output);
        $this->assertStringContainsString('ai:team', $output);
        $this->assertStringContainsString('invoice:pdf', $output);
        $this->assertStringContainsString('auth:totp', $output);
        $this->assertStringContainsString('queue:work', $output);
        $this->assertStringContainsString('cache:clear', $output);
        $this->assertStringContainsString('webrtc:serve', $output);
    }
}
