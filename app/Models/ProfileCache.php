<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileCache extends Model
{
    use HasFactory;

    protected $table = 'profiles_cache';

    protected $fillable = [
        'minecraft_uuid',
        'profile_id',
        'cute_name',
        'raw_data',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'json',
            'fetched_at' => 'datetime',
        ];
    }
}
