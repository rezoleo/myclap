<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'clap_user';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'school_email',
        'promo',
        'alumni',
        'created_on',
        'logged_on',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'alumni' => 'boolean',
            'created_on' => 'datetime',
            'logged_on' => 'datetime',
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'username', 'username');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(VideoReaction::class, 'username', 'username');
    }

    public function hasPermission(string $identifier): bool
    {
        return $this->permissions()->where('identifier', $identifier)->exists() || $this->isAdmin();
    }

    public function hasPermissionGroup(string $group): bool
    {
        return $this->permissions()->where('identifier', 'LIKE', "{$group}.%")->exists() || $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->permissions()->where('identifier', 'admin')->exists();
    }
}
