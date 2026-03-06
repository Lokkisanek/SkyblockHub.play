<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'product_name',
        'buy_price',
        'quantity',
        'purchased_at',
        'sold_price',
        'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'buy_price'    => 'decimal:2',
            'sold_price'   => 'decimal:2',
            'quantity'     => 'integer',
            'purchased_at' => 'datetime',
            'sold_at'      => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate profit/loss based on current or sold price.
     */
    public function profitAt(float $currentPrice): float
    {
        $sellPrice = $this->sold_price ?? $currentPrice;
        return ($sellPrice - $this->buy_price) * $this->quantity;
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('sold_at');
    }

    public function scopeClosed($query)
    {
        return $query->whereNotNull('sold_at');
    }
}
