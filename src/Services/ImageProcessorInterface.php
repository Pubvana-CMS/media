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
     * Crop a region of the image.
     *
     * @param int $x      Left offset in pixels
     * @param int $y      Top offset in pixels
     * @param int $width  Width of the crop region
     * @param int $height Height of the crop region
     */
    public function crop(int $x, int $y, int $width, int $height): static;

    /**
     * Rotate the image clockwise by the given degrees.
     *
     * @param int $degrees Rotation angle (90, 180, 270)
     */
    public function rotate(int $degrees): static;

    /**
     * Flip the image.
     *
     * @param string $direction 'horizontal' or 'vertical'
     */
    public function flip(string $direction): static;

    /**
     * Apply sharpening.
     */
    public function sharpen(): static;

    /**
     * Adjust brightness.
     *
     * @param int $level -100 (darker) to 100 (brighter), 0 = no change
     */
    public function brightness(int $level): static;

    /**
     * Adjust contrast.
     *
     * @param int $level -100 (less) to 100 (more), 0 = no change
     */
    public function contrast(int $level): static;

    /**
     * Auto-orient the image based on EXIF orientation data.
     */
    public function autoOrient(): static;

    /**
     * Strip EXIF and other metadata from the loaded image.
     */
    public function stripExif(): static;

    /**
     * Convert to WebP format and save.
     */
    public function toWebp(string $outputPath, int $quality = 85): void;

    /**
     * Save in the format determined by the output path extension.
     * Quality is 0-100 for JPEG/WebP, ignored for PNG/GIF.
     */
    public function save(string $outputPath, ?int $quality = null): void;

    /**
     * Get image dimensions and format info.
     *
     * @return array{width: int, height: int, mime: string}
     */
    public function getInfo(string $path): array;

    /**
     * Read EXIF/metadata from an image file.
     *
     * @return array<string, string> Key-value pairs of readable metadata
     */
    public function getExif(string $path): array;

    /**
     * Report which editing operations this processor supports.
     *
     * @return string[]
     */
    public function capabilities(): array;
}
