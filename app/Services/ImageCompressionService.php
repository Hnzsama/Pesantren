<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageCompressionService
{
    /**
     * Store an uploaded file as WebP.
     *
     * @param UploadedFile $file
     * @param string $path
     * @param string $filename
     * @param int $quality
     * @return string
     */
    public function storeAsWebP(UploadedFile $file, string $path, string $filename, int $quality = 80): string
    {
        // Ensure the directory exists
        if (!Storage::disk('public')->exists($path)) {
            Storage::disk('public')->makeDirectory($path);
        }

        $image = imagecreatefromstring(file_get_contents($file->getRealPath()));
        $fullPath = $path . '/' . $filename . '.webp';
        
        // Save to temporary path first because imagewebp writes to filesystem
        $tempPath = sys_get_temp_dir() . '/' . Str::random(40) . '.webp';
        imagewebp($image, $tempPath, $quality);
        imagedestroy($image);

        // Move to storage
        Storage::disk('public')->put($fullPath, file_get_contents($tempPath));
        unlink($tempPath);

        return $fullPath;
    }
}
