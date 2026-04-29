<?php

declare(strict_types=1);

namespace Pubvana\Media\Services;

/**
 * ImageProcessorInterface implementation backed by the Imagick (ImageMagick) extension.
 *
 * Preferred when available — better quality (Lanczos resampling), wider format support,
 * and lower memory pressure on large images compared to GD.
 *
 * @package Pubvana\Media\Services
 */
class ImagickProcessor implements ImageProcessorInterface
{
    private \Imagick $image;

    public function load(string $path): static
    {
        $this->image = new \Imagick($path);
        return $this;
    }

    public function resize(int $width): static
    {
        $origWidth  = $this->image->getImageWidth();
        $origHeight = $this->image->getImageHeight();

        if ($origWidth <= $width) {
            return $this;
        }

        $height = (int) round($origHeight * ($width / $origWidth));
        $this->image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);

        return $this;
    }

    public function crop(int $width, int $height): static
    {
        $this->image->cropThumbnailImage($width, $height);
        return $this;
    }

    public function toWebp(string $outputPath, int $quality = 85): void
    {
        $this->image->setImageFormat('webp');
        $this->image->setImageCompressionQuality($quality);
        $this->image->writeImage($outputPath);
        $this->image->clear();
    }

    public function getInfo(string $path): array
    {
        $img = new \Imagick($path);
        $info = [
            'width'  => $img->getImageWidth(),
            'height' => $img->getImageHeight(),
            'mime'   => $img->getImageMimeType(),
        ];
        $img->clear();

        return $info;
    }

    public function stripExif(string $path): void
    {
        $img = new \Imagick($path);
        $img->stripImage();
        $img->writeImage($path);
        $img->clear();
    }
}
