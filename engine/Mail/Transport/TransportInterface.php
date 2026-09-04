<?php
declare(strict_types=1);

namespace Oshim\Mail\Transport;

use Oshim\Mail\Mailable;

interface TransportInterface
{
    public function send(Mailable $mailable): bool;
}
