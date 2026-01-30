<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoService
{
    public function getVideoDuration(string $filePath): ?int
    {
        $fullPath = Storage::disk('local')->path($filePath);

        if (! file_exists($fullPath)) {
            return null;
        }

        $command = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($fullPath)
        );

        $output = shell_exec($command);

        if ($output === null || trim($output) === '') {
            Log::warning("Failed to get video duration for: {$filePath}");

            return null;
        }

        $duration = (int) round((float) trim($output));

        return $duration > 0 ? $duration : null;
    }

    public function updateDuration(Video $video): ?int
    {
        if (! $video->file_identifier) {
            return null;
        }

        $duration = $this->getVideoDuration($video->file_identifier);

        if ($duration !== null) {
            $video->duration = $duration;
            $video->save();
        }

        return $duration;
    }

    public function checkAndUpdateDuration(Video $video): ?int
    {
        if (! $video->file_identifier) {
            return null;
        }

        $newDuration = $this->getVideoDuration($video->file_identifier);

        if ($newDuration !== null && $newDuration !== $video->duration) {
            $video->duration = $newDuration;
            $video->save();
        }

        return $video->duration;
    }
}
