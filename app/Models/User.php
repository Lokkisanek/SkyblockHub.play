<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'discord_id',
        'discord_username',
        'discord_avatar',
        'minecraft_uuid',
        'minecraft_username',
        'password',
        'karma_score',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'karma_score' => 'integer',
        ];
    }

    public function dungeonParties(): HasMany
    {
        return $this->hasMany(DungeonParty::class);
    }

    public function karmaVotesGiven(): HasMany
    {
        return $this->hasMany(KarmaVote::class, 'voter_id');
    }

    public function karmaVotesReceived(): HasMany
    {
        return $this->hasMany(KarmaVote::class, 'target_id');
    }
}
