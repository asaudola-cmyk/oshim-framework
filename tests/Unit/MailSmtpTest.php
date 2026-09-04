<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Mail\Mailable;
use Oshim\Mail\Mailer;
use Oshim\Mail\Mail;
use Oshim\Mail\Transport\ArrayTransport;

final class MailSmtpTest extends TestCase
{
    public function testMailableConstructionAndSending(): void
    {
        $transport = new ArrayTransport();
        $mailer = new Mailer($transport);

        $mail = $mailer->to('client@example.com', 'Client Name')
            ->from('billing@oshim.cloud', 'OSHIM Billing')
            ->subject('Invoice #INV-2026-99')
            ->html('<h1>Invoice Paid</h1><p>Thank you for using OSHIM Cloud.</p>')
            ->text('Invoice Paid. Thank you for using OSHIM Cloud.');

        $sent = $mailer->send($mail);

        $this->assertTrue($sent);
        $this->assertSame(1, $transport->count());
        $this->assertSame('billing@oshim.cloud', $transport->sentMails[0]->getFrom());
        $this->assertSame('Invoice #INV-2026-99', $transport->sentMails[0]->getSubject());
        $this->assertStringContainsString('Invoice Paid', $transport->sentMails[0]->getHtml());
    }
}
