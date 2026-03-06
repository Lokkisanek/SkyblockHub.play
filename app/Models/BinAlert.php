<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_name',
        'threshold_price',
        'is_active',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold_price'   => 'decimal:2',
            'is_active'         => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
