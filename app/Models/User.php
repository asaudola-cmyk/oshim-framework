<?php
declare(strict_types=1);

namespace App\Models;

use Oshim\Database\ORM\Model;
use Oshim\Database\ORM\Relations\HasMany;
use Oshim\Database\ORM\Traits\HasTimestamps;

class User extends Model
{
    use HasTimestamps;

    protected string $table = 'users';
    
    protected array $fillable = [
        'name',
        'email',
        'password',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'user_id', 'id');
    }
}
