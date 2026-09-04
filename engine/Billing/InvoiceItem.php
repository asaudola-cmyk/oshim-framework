<?php
declare(strict_types=1);

namespace Oshim\Billing;

class InvoiceItem
{
    public function __construct(
        public string $description,
        public int $qty,
        public int $unitPriceCents,
        public ?string $itemType = 'service',
        public ?string $referenceId = null
    ) {
    }

    public function getTotalCents(): int
    {
        return $this->qty * $this->unitPriceCents;
    }

    public function toArray(): array
    {
        return [
            'item_type' => $this->itemType,
            'reference_id' => $this->referenceId,
            'description' => $this->description,
            'qty' => $this->qty,
            'unit_price_cents' => $this->unitPriceCents,
            'total_cents' => $this->getTotalCents(),
        ];
    }
}
