<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoView extends Model
{
    protected $table = 'video_view';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'video_token',
        'php_sid',
        'playback_sid',
        'username',
        'count_as_view',
        'watch_time',
        'view_source',
        'device_type',
        'browser',
        'os',
        'created_on',
        'updated_on',
    ];

    protected function casts(): array
    {
        return [
            'count_as_view' => 'boolean',
            'watch_time' => 'integer',
            'created_on' => 'datetime',
            'updated_on' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_token', 'token');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }
}
