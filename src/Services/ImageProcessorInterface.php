<?php

declare(strict_types=1);

namespace Pubvana\Media\Services;

interface ImageProcessorInterface
{
    /**
     * Load an image from a file path.
     */
    public function load(string $path): static;

    /**
     * Resize proportionally to fit within the given width.
     */
    public function resize(int $width): static;

    /**
     * Resize and crop to exact dimensions (center crop).
     */
    public function crop(int $width, int $height): static;

    /**
     * Convert to WebP format and save.
     */
    public function toWebp(string $outputPath, int $quality = 85): void;

    /**
     * Get image dimensions and format info.
     *
     * @return array{width: int, height: int, mime: string}
     */
    public function getInfo(string $path): array;

    /**
     * Strip EXIF and other metadata from an image file in place.
     */
    public function stripExif(string $path): void;
}
