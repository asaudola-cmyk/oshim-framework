<?php
declare(strict_types=1);

namespace App\Models;

use Oshim\Database\ORM\Model;
use Oshim\Database\ORM\Relations\BelongsTo;

/**
 * Invoice Active Record ORM Model.
 * Represents billing invoices with automatic status management.
 */
class Invoice extends Model
{
    protected string $table = 'invoices';
    protected array $fillable = [
        'user_id',
        'instance_id',
        'invoice_number',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'currency',
        'status',
        'due_date',
        'paid_at',
    ];
    protected array $casts = [
        'subtotal_cents' => 'int',
        'tax_cents'      => 'int',
        'total_cents'    => 'int',
        'user_id'        => 'int',
        'instance_id'    => 'int',
    ];

    /**
     * Mark invoice as paid with timestamp.
     */
    public function markAsPaid(): bool
    {
        $this->status = 'paid';
        $this->paid_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Get the associated instance.
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'instance_id');
    }
}
