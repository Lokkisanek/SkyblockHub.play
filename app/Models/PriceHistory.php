<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    use HasFactory;

    protected $table = 'price_history';

    protected $fillable = [
        'bazaar_item_id',
        'sell_price',
        'buy_price',
        'sell_volume',
        'buy_volume',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'sell_price' => 'decimal:2',
            'buy_price' => 'decimal:2',
            'sell_volume' => 'integer',
            'buy_volume' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function bazaarItem(): BelongsTo
    {
        return $this->belongsTo(BazaarItem::class);
    }
}
