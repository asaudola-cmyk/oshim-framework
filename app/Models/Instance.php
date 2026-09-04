<?php
declare(strict_types=1);

namespace App\Models;

use Oshim\Database\ORM\Model;
use Oshim\Database\ORM\Relations\HasMany;
use Oshim\Database\ORM\Relations\BelongsTo;
use Oshim\Database\ORM\Traits\SoftDeletes;

/**
 * Instance Active Record ORM Model.
 * Represents sovereign compute/VPS instances in the database.
 */
class Instance extends Model
{
    use SoftDeletes;

    protected string $table = 'instances';
    protected array $fillable = [
        'user_id',
        'hostname',
        'cores',
        'memory_mb',
        'disk_gb',
        'os',
        'ip_address',
        'lifecycle_status',
        'next_due_date',
        'suspended_at',
        'terminated_at',
        'deleted_at',
    ];
    protected array $casts = [
        'cores'     => 'int',
        'memory_mb' => 'int',
        'disk_gb'   => 'int',
        'user_id'   => 'int',
    ];

    /**
     * Get all invoices associated with this instance.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'instance_id');
    }
}
