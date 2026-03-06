<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BazaarItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'category',
        'sell_price',
        'buy_price',
        'sell_volume',
        'buy_volume',
        'sell_orders',
        'buy_orders',
        'sell_moving_week',
        'buy_moving_week',
        'last_updated',
    ];

    protected function casts(): array
    {
        return [
            'sell_price' => 'decimal:2',
            'buy_price' => 'decimal:2',
            'sell_volume' => 'integer',
            'buy_volume' => 'integer',
            'sell_orders' => 'integer',
            'buy_orders' => 'integer',
            'sell_moving_week' => 'decimal:2',
            'buy_moving_week' => 'decimal:2',
            'last_updated' => 'datetime',
        ];
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }
}
