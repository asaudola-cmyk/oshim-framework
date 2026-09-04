<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Bootstrap.php';

use Oshim\Bootstrap;
use Oshim\Cli\CliApplication;
use Oshim\Cli\Commands\ServeCommand;
use Oshim\Cli\Commands\TestCommand;
use Oshim\Cli\Commands\MigrateCommand;
use Oshim\Cli\Commands\RollbackCommand;
use Oshim\Cli\Commands\SeedCommand;
use Oshim\Cli\Commands\MakeModelCommand;
use Oshim\Cli\Commands\MakeMigrationCommand;
use Oshim\Cli\Commands\MakeControllerCommand;
use Oshim\Cli\Commands\MakeComponentCommand;
use Oshim\Cli\Commands\KeyGenerateCommand;
use Oshim\Cli\Commands\UniversalInfoCommand;
use Oshim\Cli\Commands\TurboServeCommand;
use Oshim\Cli\Commands\TurboBenchCommand;
use Oshim\Cli\Commands\MobileBuildCommand;
use Oshim\Cli\Commands\DesktopServeCommand;
use Oshim\Cli\Commands\AiChatCommand;
use Oshim\Cli\Commands\AiRagCommand;
use Oshim\Cli\Commands\AiTeamCommand;
use Oshim\Cli\Commands\PdfInvoiceCommand;
use Oshim\Cli\Commands\TotpQrCommand;
use Oshim\Cli\Commands\QueueWorkCommand;
use Oshim\Cli\Commands\CacheClearCommand;
use Oshim\Cli\Commands\AppCreateCommand;
use Oshim\Cli\Commands\AppBundleCommand;
use Oshim\Cli\Commands\AppRunCommand;
use Oshim\Cli\Commands\BillingCronCommand;
use Oshim\Cli\Commands\DnsServeCommand;
use Oshim\Cli\Commands\DnsStartCommand;
use Oshim\Cli\Commands\NodeStartCommand;
use Oshim\Cli\Commands\S3ServeCommand;
use Oshim\Cli\Commands\SslIssueCommand;
use Oshim\Cli\Commands\VmSpawnCommand;
use Oshim\Cli\Commands\ScheduleRunCommand;
use Oshim\Cli\Commands\MakeCrudCommand;
use Oshim\Cli\Commands\PluginVerifyCommand;
use Oshim\Cli\Commands\SelfUpdateCommand;
use Oshim\Cli\Commands\SwarmInitCommand;
use Oshim\Cli\Commands\SwarmJoinCommand;
use Oshim\Cli\Commands\SwarmStatusCommand;
use Oshim\Cli\Commands\SwarmLeaveCommand;
use Oshim\Cli\Commands\WebRtcServeCommand;
use Oshim\Cli\Commands\WasmRunCommand;

// Boot Framework Kernel
$app = Bootstrap::boot(dirname(__DIR__, 2));

// Initialize CLI Application
$cli = new CliApplication($app);

// Register Built-in Commands
$cli->register(new ServeCommand())
    ->register(new TestCommand())
    ->register(new MigrateCommand())
    ->register(new RollbackCommand())
    ->register(new SeedCommand())
    ->register(new MakeModelCommand())
    ->register(new MakeMigrationCommand())
    ->register(new MakeControllerCommand())
    ->register(new MakeComponentCommand())
    ->register(new KeyGenerateCommand())
    ->register(new UniversalInfoCommand())
    ->register(new TurboServeCommand())
    ->register(new TurboBenchCommand())
    ->register(new MobileBuildCommand())
    ->register(new DesktopServeCommand())
    ->register(new AiChatCommand())
    ->register(new AiRagCommand())
    ->register(new AiTeamCommand())
    ->register(new PdfInvoiceCommand())
    ->register(new TotpQrCommand())
    ->register(new QueueWorkCommand())
    ->register(new CacheClearCommand())
    ->register(new AppCreateCommand())
    ->register(new AppBundleCommand())
    ->register(new AppRunCommand())
    ->register(new BillingCronCommand())
    ->register(new DnsServeCommand())
    ->register(new DnsStartCommand())
    ->register(new NodeStartCommand())
    ->register(new S3ServeCommand())
    ->register(new SslIssueCommand())
    ->register(new VmSpawnCommand())
    ->register(new ScheduleRunCommand())
    ->register(new MakeCrudCommand())
    ->register(new PluginVerifyCommand())
    ->register(new SelfUpdateCommand())
    ->register(new SwarmInitCommand())
    ->register(new SwarmJoinCommand())
    ->register(new SwarmStatusCommand())
    ->register(new SwarmLeaveCommand())
    ->register(new WebRtcServeCommand())
    ->register(new WasmRunCommand());

// Execute Command
$exitCode = $cli->run($argv ?? []);
exit($exitCode);
