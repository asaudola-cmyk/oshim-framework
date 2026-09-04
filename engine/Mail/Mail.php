<?php
declare(strict_types=1);

namespace Oshim\Mail;

use Oshim\Mail\Transport\TransportInterface;

class Mail
{
    private static ?Mailer $mailer = null;

    public static function getMailer(): Mailer
    {
        if (self::$mailer === null) {
            self::$mailer = new Mailer();
        }
        return self::$mailer;
    }

    public static function setTransport(TransportInterface $transport): void
    {
        self::getMailer()->setTransport($transport);
    }

    public static function to(string|array $address, string $name = ''): Mailable
    {
        return self::getMailer()->to($address, $name);
    }

    public static function send(Mailable $mailable): bool
    {
        return self::getMailer()->send($mailable);
    }
}
