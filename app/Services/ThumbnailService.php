<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ThumbnailService
{
    private ImageManager $manager;

    /**
     * Thumbnail sizes to generate (height => [width, height])
     * - 1080: Video player poster (full screen)
     * - 480: Video cards in grids
     * - 120: Small thumbnails (search, playlists)
     */
    public const SIZES = [
        1080 => [1920, 1080],
        480 => [854, 480],
        120 => [213, 120],
    ];

    private const THUMBNAILS_DIR = 'thumbnails';

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    public function generateIdentifier(): string
    {
        return Str::random(10);
    }

    /**
     * Store a thumbnail and generate all size variants
     *
     * @param  UploadedFile  $file  The uploaded image file
     * @return string The thumbnail identifier
     *
     * @throws Exception If the image type is invalid
     */
    public function store(UploadedFile $file): string
    {
        $imageType = exif_imagetype($file->getRealPath());
        if ($imageType !== IMAGETYPE_JPEG && $imageType !== IMAGETYPE_PNG) {
            throw new Exception('La miniature doit être un fichier PNG ou JPEG.');
        }

        $identifier = $this->generateIdentifier();
        $this->ensureDirectoryExists();

        // Store original file temporarily to generate variants
        $originalPath = $file->getRealPath();

        // Generate all size variants
        foreach (self::SIZES as [$width, $height]) {
            $this->generateVariant($originalPath, $identifier, $width, $height);
        }

        return $identifier;
    }

    private function generateVariant(string $sourcePath, string $identifier, int $width, int $height): void
    {
        $image = $this->manager->read($sourcePath);

        // Calculate dimensions maintaining 16:9 aspect ratio
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Scale to fit within target dimensions
        if ($originalWidth / $originalHeight > $width / $height) {
            // Wider than target, fit by width
            $newWidth = $width;
            $newHeight = (int) ceil(($originalHeight / $originalWidth) * $width);
        } else {
            // Taller than target, fit by height
            $newHeight = $height;
            $newWidth = (int) ceil(($originalWidth / $originalHeight) * $height);
        }

        $image->scale($newWidth, $newHeight);

        $filename = $this->getVariantFilename($identifier, $height);
        $outputPath = Storage::disk('local')->path(self::THUMBNAILS_DIR.'/'.$filename);
        $image->toJpeg(quality: 85)->save($outputPath);
    }

    public function getVariantFilename(string $identifier, int $height): string
    {
        return "{$identifier}:{$height}.jpg";
    }

    public function getVariantPath(string $identifier, int $height): string
    {
        return self::THUMBNAILS_DIR.'/'.$this->getVariantFilename($identifier, $height);
    }

    public function delete(string $identifier): void
    {
        foreach (array_keys(self::SIZES) as $height) {
            $path = $this->getVariantPath($identifier, $height);
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    private function ensureDirectoryExists(): void
    {
        $dir = Storage::disk('local')->path(self::THUMBNAILS_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public static function getAvailableSizes(): array
    {
        return array_keys(self::SIZES);
    }
}
