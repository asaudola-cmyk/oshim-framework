<?php
declare(strict_types=1);

namespace Oshim\Billing\Gateways;

class PaymentRedirect
{
    public function __construct(
        public string $url,
        public string $method = 'GET',
        public array $params = []
    ) {
    }
}
