<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoReaction extends Model
{
    protected $table = 'video_reaction';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'video_token',
        'username',
        'created_on',
    ];

    protected function casts(): array
    {
        return [
            'created_on' => 'datetime',
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
