<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Billing\Pdf\PdfInvoiceBuilder;

class PdfInvoiceCommand extends Command
{
    protected string $name = 'invoice:pdf';
    protected string $description = 'Generate a branded Vector PDF invoice using pure PHP zero-dependency binary engine';

    protected function configure(): void
    {
        $this->addOption('output', 'o', Input::VALUE_OPTIONAL, 'Target PDF file path', 'storage/invoice-sample.pdf');
    }

    public function execute(Input $input, Output $output): int
    {
        $targetFile = $input->getOption('output') ?? 'storage/invoice-sample.pdf';

        $output->writeln("\n<info>📄 Generating Vector PDF Invoice...</info>");

        $builder = new PdfInvoiceBuilder();
        $pdf = $builder->build([
            'invoice_number' => 'INV-' . date('Ymd') . '-8899',
            'date' => date('Y-m-d'),
            'status' => 'PAID',
            'client_name' => 'Sovereign Enterprise Tech Ltd',
            'client_email' => 'finance@sovereign-tech.com',
            'items' => [
                ['description' => 'Dedicated Bare-Metal KVM MicroVM (8 vCPU, 32GB RAM, 500GB NVMe)', 'qty' => 2, 'price' => 85.00],
                ['description' => 'Anycast DDoS Shield & Dedicated IPv4 Subnet /29', 'qty' => 1, 'price' => 15.00],
                ['description' => 'Enterprise DNS & TLS Automated Provisioning', 'qty' => 1, 'price' => 10.00],
            ],
            'currency' => '$',
        ]);

        $rendered = $pdf->render();

        $dir = dirname($targetFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents($targetFile, $rendered);
        $fileSize = strlen($rendered);

        $output->writeln("<info>✔ PDF Invoice Created Successfully!</info>");
        $output->writeln("  Destination: <comment>{$targetFile}</comment>");
        $output->writeln("  Binary Size: <comment>{$fileSize} bytes</comment>");
        $output->writeln("  Format: <comment>Adobe PDF 1.4 (Pure PHP Binary Stream Writer)</comment>\n");

        return 0;
    }
}
