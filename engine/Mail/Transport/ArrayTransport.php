<?php
declare(strict_types=1);

namespace Oshim\Mail\Transport;

use Oshim\Mail\Mailable;

class ArrayTransport implements TransportInterface
{
    /** @var array<Mailable> */
    public array $sentMails = [];

    public function send(Mailable $mailable): bool
    {
        $this->sentMails[] = $mailable;
        return true;
    }

    public function count(): int
    {
        return count($this->sentMails);
    }

    public function flush(): void
    {
        $this->sentMails = [];
    }
}
