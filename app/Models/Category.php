<?php

namespace App\Models;

use App\Enums\ContentAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $table = 'category';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'slug',
        'label',
        'description',
        'created_by',
        'created_on',
    ];

    protected function casts(): array
    {
        return [
            'created_on' => 'datetime',
        ];
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(
            Video::class,
            'video_category',
            'category_slug',
            'video_token',
            'slug',
            'token'
        );
    }

    public function publishedVideos(?User $user = null): BelongsToMany
    {
        $query = $this->videos()->published();

        if ($user) {
            $query->whereIn('access', [
                ContentAccess::CENTRALIENS->value,
                ContentAccess::PUBLIC->value,
            ]);
        } else {
            $query->where('access', ContentAccess::PUBLIC->value);
        }

        return $query;
    }

    public function getVideoCountAttribute(): int
    {
        return $this->videos()->published()->count();
    }
}
