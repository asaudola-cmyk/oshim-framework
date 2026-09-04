<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Container\Container;
use Oshim\Lifecycle\ServiceLifecycleManager;

class BillingCronCommand extends Command
{
    protected string $name = 'billing:cron';
    protected string $description = 'Run automated billing lifecycle check (invoices, reminders, suspensions, terminations)';

    public function execute(Input $input, Output $output): int
    {
        $output->writeln("<cyan>Running daily automated billing and service lifecycle check...</cyan>");

        $container = Container::getInstance();
        /** @var ServiceLifecycleManager $manager */
        $manager = $container->make(ServiceLifecycleManager::class);

        $stats = $manager->runDailyLifecycleCheck();

        $output->writeln(" - Renewal invoices generated (T-7): <green>{$stats['renewal_invoices_generated']}</green>");
        $output->writeln(" - Payment reminders dispatched (T-3): <yellow>{$stats['reminders_sent']}</yellow>");
        $output->writeln(" - Grace period overdue transitions (T-0): <yellow>{$stats['grace_periods_started']}</yellow>");
        $output->writeln(" - Automated suspensions enforced (T+7): <red>{$stats['auto_suspended']}</red>");
        $output->writeln(" - Automated terminations purged (T+14): <red>{$stats['auto_terminated']}</red>");

        $output->success("Billing lifecycle cron run completed successfully.");
        return 0;
    }
}
