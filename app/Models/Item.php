<?php
declare(strict_types=1);

namespace App\Models;

use Oshim\Database\ORM\Model;

class Item extends Model
{
    protected string $table = 'items';
    protected array $fillable = ['name', 'status', 'description'];
}