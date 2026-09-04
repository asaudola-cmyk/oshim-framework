<?php
declare(strict_types=1);

namespace Oshim\Mail;

use Oshim\Mail\Transport\TransportInterface;
use Oshim\Mail\Transport\ArrayTransport;
use Oshim\Mail\Transport\SmtpTransport;

class Mailer
{
    private TransportInterface $transport;

    public function __construct(?TransportInterface $transport = null)
    {
        $this->transport = $transport ?? new ArrayTransport();
    }

    public function setTransport(TransportInterface $transport): void
    {
        $this->transport = $transport;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function to(string|array $address, string $name = ''): Mailable
    {
        $mailable = new Mailable();
        $mailable->to($address, $name);
        return $mailable;
    }

    public function send(Mailable $mailable): bool
    {
        return $this->transport->send($mailable);
    }
}
