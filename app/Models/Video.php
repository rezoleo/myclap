<?php

namespace App\Models;

use App\Enums\ContentAccess;
use App\Enums\UploadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    protected $table = 'video';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'token',
        'name',
        'description',
        'thumbnail_identifier',
        'file_identifier',
        'access',
        'upload_status',
        'views',
        'reactions',
        'duration',
        'created_on',
        'uploaded_on',
        'uploaded_by',
    ];

    protected $appends = ['thumbnail_url', 'thumbnail_urls', 'video_url', 'author'];

    protected function casts(): array
    {
        return [
            'access' => 'integer',
            'views' => 'integer',
            'reactions' => 'integer',
            'duration' => 'integer',
            'created_on' => 'date',
            'uploaded_on' => 'datetime',
            'upload_status' => 'integer',
        ];
    }

    // Relations
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'username');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'video_category',
            'video_token',
            'category_slug',
            'token',
            'slug'
        );
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(
            Playlist::class,
            'playlist_video',
            'video_token',
            'playlist_slug',
            'token',
            'slug'
        )->withPivot('position');
    }

    public function videoReactions(): HasMany
    {
        return $this->hasMany(VideoReaction::class, 'video_token', 'token');
    }

    public function videoViews(): HasMany
    {
        return $this->hasMany(VideoView::class, 'video_token', 'token');
    }

    // Scopes
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('upload_status', UploadStatus::UPLOAD_END->value);
    }

    public function scopeAccessibleBy(Builder $query, ?User $user): Builder
    {
        if ($user) {
            return $query->whereIn('access', [
                ContentAccess::CENTRALIENS->value,
                ContentAccess::PUBLIC->value,
            ]);
        }

        return $query->where('access', ContentAccess::PUBLIC->value);
    }

    // Accessors
    public function getAuthorAttribute(): string
    {
        return $this->uploaded_by;
    }

    public function getThumbnailUrlAttribute(): string
    {
        return route('watch.media.thumbnail', [
            'token' => $this->token,
            'size' => 1080,
        ]);
    }

    public function getThumbnailUrlsAttribute(): array
    {
        return [
            '1080' => route('watch.media.thumbnail', ['token' => $this->token, 'size' => 1080]),
            '480' => route('watch.media.thumbnail', ['token' => $this->token, 'size' => 480]),
            '120' => route('watch.media.thumbnail', ['token' => $this->token, 'size' => 120]),
        ];
    }

    public function getVideoUrlAttribute(): string
    {
        return route('watch.media.video', ['token' => $this->token]);
    }

    public function getAccessEnumAttribute(): ContentAccess
    {
        return ContentAccess::from($this->access);
    }

    public function getUploadStatusEnumAttribute(): UploadStatus
    {
        return UploadStatus::from($this->upload_status);
    }

    public function isPublished(): bool
    {
        return $this->upload_status === UploadStatus::UPLOAD_END->value;
    }

    /**
     * Sync categories with the video using slugs.
     */
    public function syncCategories(array $categorySlugs): void
    {
        $validSlugs = Category::whereIn('slug', $categorySlugs)->pluck('slug')->toArray();

        \DB::table('video_category')->where('video_token', $this->token)->delete();

        foreach ($validSlugs as $slug) {
            \DB::table('video_category')->insert([
                'video_token' => $this->token,
                'category_slug' => $slug,
            ]);
        }
    }
}
