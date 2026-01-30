<?php

namespace App\Services;

use App\Enums\UploadStatus;
use App\Models\Video;
use App\Models\VideoUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoUploadService
{
    private const MIN_CHUNK_SIZE = 0.1 * 1024 * 1024; // 0.1 MB

    private const MAX_CHUNK_SIZE = 10 * 1024 * 1024;  // 10 MB

    public function __construct(
        private readonly VideoService $videoService
    ) {}

    public function initUpload(Video $video, string $fileName, int $fileSize, string $username): array
    {
        if ($video->upload_status === UploadStatus::UPLOAD_END->value) {
            throw new \Exception('Vidéo déjà uploadée');
        }

        $upload = VideoUpload::where('video_token', $video->token)->first();
        $startIndex = 0;

        if ($upload) {
            // Resume upload
            if ($upload->file_size != $fileSize) {
                throw new \Exception('Le fichier doit être identique à celui que vous aviez commencé à envoyer.');
            }

            if (Storage::disk('local')->exists($upload->file_identifier)) {
                $path = Storage::disk('local')->path($upload->file_identifier);
                $startIndex = filesize($path);
            } else {
                // Recreate the file
                Storage::disk('local')->put($upload->file_identifier, '');
            }
        } else {
            // Create new upload
            $fileIdentifier = 'video_upload/'.Str::random(40);
            Storage::disk('local')->put($fileIdentifier, '');

            $upload = VideoUpload::create([
                'video_token' => $video->token,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'file_identifier' => $fileIdentifier,
                'created_by' => $username,
            ]);

            $video->upload_status = UploadStatus::UPLOAD_INIT->value;
            $video->save();
        }

        return [
            'startIndex' => $startIndex,
            'chunkSize' => (int) self::MIN_CHUNK_SIZE,
        ];
    }

    public function processChunk(Video $video, $chunkFile, int $startIndex, int $chunkSize): array
    {
        $upload = VideoUpload::where('video_token', $video->token)->firstOrFail();

        $path = Storage::disk('local')->path($upload->file_identifier);
        $currentSize = file_exists($path) ? filesize($path) : 0;

        if ($startIndex !== $currentSize) {
            throw new \Exception("Index incorrect : attendu {$currentSize}, reçu {$startIndex}");
        }

        // Read and append the chunk
        $chunkContent = file_get_contents($chunkFile->getRealPath());
        file_put_contents($path, $chunkContent, FILE_APPEND);

        $newSize = filesize($path);

        if ($newSize >= $upload->file_size) {
            return [
                'completed' => true,
                'startIndex' => $newSize,
                'chunkSize' => $chunkSize,
            ];
        }

        // Calculate adaptive chunk size (target 3 seconds per chunk)
        $adaptiveChunkSize = min(
            (int) self::MAX_CHUNK_SIZE,
            max((int) self::MIN_CHUNK_SIZE, $chunkSize)
        );

        return [
            'completed' => false,
            'startIndex' => $newSize,
            'chunkSize' => $adaptiveChunkSize,
        ];
    }

    public function finalizeUpload(Video $video): void
    {
        $upload = VideoUpload::where('video_token', $video->token)->firstOrFail();

        $tempPath = $upload->file_identifier;

        // Generate random 10-character identifier for video file
        $videoIdentifier = Str::random(10);
        $finalIdentifier = 'videos/'.$videoIdentifier.'.mp4';

        // Check file size matches
        $path = Storage::disk('local')->path($tempPath);
        if (filesize($path) !== $upload->file_size) {
            throw new \Exception("Toute la ressource n'a pas été correctement téléversée. Veuillez recommencer.");
        }

        // Ensure videos directory exists
        $videosDir = Storage::disk('local')->path('videos');
        if (! is_dir($videosDir)) {
            mkdir($videosDir, 0755, true);
        }

        // Move the file
        Storage::disk('local')->move($tempPath, $finalIdentifier);

        $video->file_identifier = $finalIdentifier;
        $video->upload_status = UploadStatus::UPLOAD_END->value;
        $video->uploaded_on = now();
        $video->save();

        $this->videoService->updateDuration($video);
        $upload->delete();
    }

    public function resetUpload(Video $video): void
    {
        $upload = VideoUpload::where('video_token', $video->token)->first();

        if ($upload) {
            if (Storage::disk('local')->exists($upload->file_identifier)) {
                Storage::disk('local')->delete($upload->file_identifier);
            }
            $upload->delete();
        }

        $video->upload_status = UploadStatus::UPLOAD_NULL->value;
        $video->save();
    }

    public function getUploadProgress(Video $video): ?array
    {
        $upload = VideoUpload::where('video_token', $video->token)->first();

        if (! $upload) {
            return null;
        }

        $path = Storage::disk('local')->path($upload->file_identifier);
        $currentSize = file_exists($path) ? filesize($path) : 0;

        return [
            'fileName' => $upload->file_name,
            'fileSize' => $upload->file_size,
            'uploadedSize' => $currentSize,
            'percentage' => $upload->file_size > 0 ? round(($currentSize / $upload->file_size) * 100, 2) : 0,
        ];
    }
}
