<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BinSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_name',
        'auction_uuid',
        'price',
        'tier',
        'seller_username',
        'ends_at',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'price'       => 'decimal:2',
            'ends_at'     => 'datetime',
            'recorded_at' => 'datetime',
        ];
    }
}
