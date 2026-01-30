<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoUpload extends Model
{
    protected $table = 'video_upload';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'video_token',
        'file_name',
        'file_size',
        'file_identifier',
        'created_by',
        'created_on',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'created_on' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_token', 'token');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'username');
    }
}
