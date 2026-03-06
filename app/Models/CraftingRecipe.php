<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CraftingRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'result_item_id',
        'result_item_name',
        'result_quantity',
        'ingredients',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'ingredients'     => 'array',
            'result_quantity' => 'integer',
        ];
    }
}
