<?php
declare(strict_types=1);

namespace App\Models;

use Oshim\Database\ORM\Model;
use Oshim\Database\ORM\Relations\BelongsTo;
use Oshim\Database\ORM\Traits\HasTimestamps;

class Document extends Model
{
    use HasTimestamps;

    protected string $table = 'documents';
    
    protected array $fillable = [
        'user_id',
        'title',
        'content',
        'prompt',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
