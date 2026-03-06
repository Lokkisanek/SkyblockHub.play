<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DungeonParty extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'floor',
        'class',
        'catacombs_level',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'catacombs_level' => 'integer',
            'is_active' => 'boolean',
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
