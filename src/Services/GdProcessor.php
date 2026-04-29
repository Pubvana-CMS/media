<?php

declare(strict_types=1);

namespace Pubvana\Media\Services;

/**
 * ImageProcessorInterface implementation backed by the GD extension.
 *
 * Fallback processor — GD ships with nearly every PHP install, so this ensures
 * image processing works even on minimal shared hosting without Imagick.
 *
 * @package Pubvana\Media\Services
 */
class GdProcessor implements ImageProcessorInterface
{
    /** @var \GdImage */
    private $image;

    private string $mime;

    public function load(string $path): static
    {
        $info       = getimagesize($path);
        $this->mime = $info['mime'];

        $this->image = match ($this->mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/gif'  => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => throw new \RuntimeException("Unsupported image type: {$this->mime}"),
        };

        // Preserve transparency
        imagealphablending($this->image, true);
        imagesavealpha($this->image, true);

        return $this;
    }

    public function resize(int $width): static
    {
        $origWidth  = imagesx($this->image);
        $origHeight = imagesy($this->image);

        if ($origWidth <= $width) {
            return $this;
        }

        $height = (int) round($origHeight * ($width / $origWidth));
        $resized = imagecreatetruecolor($width, $height);

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $this->image, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);

        imagedestroy($this->image);
        $this->image = $resized;

        return $this;
    }

    public function crop(int $width, int $height): static
    {
        $origWidth  = imagesx($this->image);
        $origHeight = imagesy($this->image);

        // Scale to cover the target dimensions
        $scaleW = $width / $origWidth;
        $scaleH = $height / $origHeight;
        $scale  = max($scaleW, $scaleH);

        $scaledWidth  = (int) round($origWidth * $scale);
        $scaledHeight = (int) round($origHeight * $scale);

        $scaled = imagecreatetruecolor($scaledWidth, $scaledHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagecopyresampled($scaled, $this->image, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $origWidth, $origHeight);

        // Center crop
        $x = (int) round(($scaledWidth - $width) / 2);
        $y = (int) round(($scaledHeight - $height) / 2);

        $cropped = imagecreatetruecolor($width, $height);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        imagecopy($cropped, $scaled, 0, 0, $x, $y, $width, $height);

        imagedestroy($this->image);
        imagedestroy($scaled);
        $this->image = $cropped;

        return $this;
    }

    public function toWebp(string $outputPath, int $quality = 85): void
    {
        imagewebp($this->image, $outputPath, $quality);
        imagedestroy($this->image);
    }

    public function getInfo(string $path): array
    {
        $info = getimagesize($path);

        return [
            'width'  => $info[0],
            'height' => $info[1],
            'mime'   => $info['mime'],
        ];
    }

    public function stripExif(string $path): void
    {
        $info = getimagesize($path);
        if ($info === false) {
            return;
        }

        $image = match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/gif'  => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => null,
        };

        if ($image === null) {
            return;
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        match ($info['mime']) {
            'image/jpeg' => imagejpeg($image, $path, 95),
            'image/png'  => imagepng($image, $path),
            'image/gif'  => imagegif($image, $path),
            'image/webp' => imagewebp($image, $path, 95),
            default      => null,
        };

        imagedestroy($image);
    }
}
