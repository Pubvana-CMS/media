<?php

declare(strict_types=1);

namespace Pubvana\Media\Services;

/**
 * Extracts poster/thumbnail frames from video files.
 *
 * Uses ffmpeg when available, otherwise returns null
 * so the caller can fall back to user-uploaded posters.
 */
class VideoThumbnailService
{
    /**
     * Check whether ffmpeg is available on this system.
     */
    public function isAvailable(): bool
    {
        $result = @exec('which ffmpeg 2>/dev/null', $output, $code);
        return $code === 0 && !empty($result);
    }

    /**
     * Extract a poster frame from a video file.
     *
     * Grabs a frame at the 1-second mark and saves it as a JPEG.
     *
     * @param string $videoPath   Absolute path to the video file
     * @param string $outputPath  Absolute path for the output JPEG
     * @return bool True if extraction succeeded
     */
    public function extract(string $videoPath, string $outputPath): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $videoPath  = escapeshellarg($videoPath);
        $outputPath = escapeshellarg($outputPath);

        $cmd = "ffmpeg -i {$videoPath} -ss 00:00:01 -vframes 1 -f image2 {$outputPath} 2>/dev/null";
        @exec($cmd, $output, $code);

        return $code === 0;
    }
}
