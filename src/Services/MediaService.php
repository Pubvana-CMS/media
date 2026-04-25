<?php

declare(strict_types=1);

namespace Pubvana\Media\Services;

use Pubvana\Media\Models\Media;

/**
 * Core business logic for media uploads, embeds, and file management.
 */
class MediaService
{
    private Media $model;
    private ImageProcessorInterface $processor;
    private VideoThumbnailService $videoThumb;
    private array $config;
    private string $publicPath;

    /**
     * @param \PDO   $pdo        Database connection
     * @param array  $config     Media config values from Config.php
     * @param string $publicPath Absolute path to the public/ directory
     */
    public function __construct(\PDO $pdo, array $config, string $publicPath)
    {
        $this->model      = new Media($pdo);
        $this->config     = $config;
        $this->publicPath = rtrim($publicPath, '/');
        $this->processor  = self::createProcessor();
        $this->videoThumb = new VideoThumbnailService();
    }

    /**
     * Pick the best available image processor.
     */
    private static function createProcessor(): ImageProcessorInterface
    {
        if (extension_loaded('imagick')) {
            return new ImagickProcessor();
        }

        if (extension_loaded('gd')) {
            return new GdProcessor();
        }

        throw new \RuntimeException('No image processing extension available. Install Imagick or GD.');
    }

    /**
     * Upload an image file.
     *
     * Stores the original, generates medium and thumbnail WebP derivatives.
     *
     * @param array $file       $_FILES entry (name, tmp_name, size, type, error)
     * @param int   $uploadedBy User ID
     * @return Media The created media record
     */
    public function uploadImage(array $file, int $uploadedBy): Media
    {
        $this->validateUpload($file, 'image');

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $hex      = bin2hex(random_bytes(16));
        $relDir   = $this->config['upload_path'] . '/' . date('Y/m');
        $absDir   = $this->publicPath . '/' . $relDir;

        $this->ensureDirectory($absDir);
        $this->ensureDirectory($absDir . '/medium');
        $this->ensureDirectory($absDir . '/thumbs');

        // Store original as-is
        $originalName = $hex . '.' . $ext;
        $originalRel  = $relDir . '/' . $originalName;
        move_uploaded_file($file['tmp_name'], $absDir . '/' . $originalName);

        // Generate medium (768px wide, proportional) as WebP
        $mediumName = $hex . '.webp';
        $this->processor
            ->load($absDir . '/' . $originalName)
            ->resize($this->config['medium_width'] ?? 768)
            ->toWebp($absDir . '/medium/' . $mediumName, $this->config['webp_quality'] ?? 85);

        // Generate thumbnail (300x200, cropped) as WebP
        $thumbName = $hex . '.webp';
        $this->processor
            ->load($absDir . '/' . $originalName)
            ->crop(
                $this->config['thumb_width'] ?? 300,
                $this->config['thumb_height'] ?? 200
            )
            ->toWebp($absDir . '/thumbs/' . $thumbName, $this->config['webp_quality'] ?? 85);

        return $this->model->createRecord([
            'type'        => 'image',
            'filename'    => $file['name'],
            'path'        => $originalRel,
            'mime_type'   => $file['type'],
            'size'        => $file['size'],
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Upload a video file.
     *
     * Stores the original and attempts ffmpeg poster extraction.
     *
     * @param array $file       $_FILES entry
     * @param int   $uploadedBy User ID
     * @return Media The created media record
     */
    public function uploadVideo(array $file, int $uploadedBy): Media
    {
        $this->validateUpload($file, 'video');

        $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $hex    = bin2hex(random_bytes(16));
        $relDir = $this->config['upload_path'] . '/' . date('Y/m');
        $absDir = $this->publicPath . '/' . $relDir;

        $this->ensureDirectory($absDir);

        // Store original
        $videoName = $hex . '.' . $ext;
        $videoRel  = $relDir . '/' . $videoName;
        move_uploaded_file($file['tmp_name'], $absDir . '/' . $videoName);

        // Attempt poster extraction
        $posterPath = null;
        $posterName = $hex . '_poster.jpg';
        $posterAbs  = $absDir . '/thumbs/' . $posterName;

        $this->ensureDirectory($absDir . '/thumbs');

        if ($this->videoThumb->extract($absDir . '/' . $videoName, $posterAbs)) {
            $posterPath = $relDir . '/thumbs/' . $posterName;
        }

        return $this->model->createRecord([
            'type'        => 'video',
            'filename'    => $file['name'],
            'path'        => $videoRel,
            'mime_type'   => $file['type'],
            'size'        => $file['size'],
            'poster_path' => $posterPath,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Store an embed (YouTube, Vimeo, etc.).
     *
     * @param string $url        The embed/watch URL
     * @param int    $uploadedBy User ID
     * @return Media The created media record
     */
    public function storeEmbed(string $url, int $uploadedBy): Media
    {
        $provider = $this->detectProvider($url);

        return $this->model->createRecord([
            'type'           => 'embed',
            'filename'       => $url,
            'embed_url'      => $url,
            'embed_provider' => $provider,
            'uploaded_by'    => $uploadedBy,
        ]);
    }

    /**
     * Upload a poster image for a video record.
     *
     * @param Media $media The video media record
     * @param array $file  $_FILES entry for the poster image
     * @return Media Updated record
     */
    public function uploadPoster(Media $media, array $file): Media
    {
        $this->validateUpload($file, 'image');

        $hex    = bin2hex(random_bytes(16));
        $relDir = dirname($media->path);
        $absDir = $this->publicPath . '/' . $relDir;

        $this->ensureDirectory($absDir . '/thumbs');

        // Remove old poster if it exists
        if ($media->poster_path) {
            $oldPoster = $this->publicPath . '/' . $media->poster_path;
            if (file_exists($oldPoster)) {
                unlink($oldPoster);
            }
        }

        $posterName = $hex . '_poster.webp';
        $this->processor
            ->load($file['tmp_name'])
            ->crop(
                $this->config['thumb_width'] ?? 300,
                $this->config['thumb_height'] ?? 200
            )
            ->toWebp($absDir . '/thumbs/' . $posterName, $this->config['webp_quality'] ?? 85);

        $media->updateMeta([
            'poster_path' => $relDir . '/thumbs/' . $posterName,
        ]);

        return $media;
    }

    /**
     * Update metadata (alt_text, title) on a media record.
     *
     * @param int   $id   Media record ID
     * @param array $data Fields to update
     * @return Media|null Updated record, or null if not found
     */
    public function updateMeta(int $id, array $data): ?Media
    {
        $media = $this->model->findById($id);
        if ($media === null) {
            return null;
        }

        $media->updateMeta($data);
        return $media;
    }

    /**
     * Delete a media record and all associated files.
     *
     * @param int $id Media record ID
     * @return bool True if deleted, false if not found
     */
    public function delete(int $id): bool
    {
        $media = $this->model->findById($id);
        if ($media === null) {
            return false;
        }

        // Delete files
        if ($media->path) {
            $basePath = $this->publicPath . '/' . $media->path;
            $dir      = dirname($basePath);
            $hex      = pathinfo($basePath, PATHINFO_FILENAME);

            // Original
            if (file_exists($basePath)) {
                unlink($basePath);
            }

            // Medium derivative
            $medium = $dir . '/medium/' . $hex . '.webp';
            if (file_exists($medium)) {
                unlink($medium);
            }

            // Thumbnail derivative
            $thumb = $dir . '/thumbs/' . $hex . '.webp';
            if (file_exists($thumb)) {
                unlink($thumb);
            }
        }

        // Poster (videos)
        if ($media->poster_path) {
            $poster = $this->publicPath . '/' . $media->poster_path;
            if (file_exists($poster)) {
                unlink($poster);
            }
        }

        $media->delete();
        return true;
    }

    /**
     * Get a paginated list of media records.
     *
     * @param int         $page    1-based page number
     * @param int         $perPage Items per page
     * @param string|null $type    Filter by type, or null for all
     * @return array{items: Media[], total: int, page: int, per_page: int}
     */
    public function list(int $page = 1, int $perPage = 24, ?string $type = null): array
    {
        return [
            'items'    => $this->model->paginate($page, $perPage, $type),
            'total'    => $this->model->countAll($type),
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Find a single media record by ID.
     */
    public function find(int $id): ?Media
    {
        return $this->model->findById($id);
    }

    /**
     * Render a media image picker widget.
     *
     * Returns HTML for a clickable thumbnail preview with an offcanvas
     * media browser. The selected image path is written to a hidden input.
     *
     * @param string $inputName    Form field name (e.g. 'avatar')
     * @param string $currentValue Current image path (relative to public/)
     * @return string Rendered HTML
     */
    public function picker(string $inputName, string $currentValue = ''): string
    {
        static $counter = 0;
        $pickerId = 'media-picker-' . (++$counter);

        ob_start();
        include __DIR__ . '/../Views/media/picker.php';
        return ob_get_clean();
    }

    /**
     * Validate an uploaded file against size and extension rules.
     *
     * @param array  $file $_FILES entry
     * @param string $kind 'image' or 'video'
     * @throws \RuntimeException On validation failure
     */
    private function validateUpload(array $file, string $kind): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed with error code: ' . $file['error']);
        }

        $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedKey = ($kind === 'image') ? 'allowed_image_ext' : 'allowed_video_ext';
        $maxKey     = ($kind === 'image') ? 'max_image_size' : 'max_video_size';

        if (!in_array($ext, $this->config[$allowedKey] ?? [], true)) {
            throw new \RuntimeException("File type not allowed: .{$ext}");
        }

        if ($file['size'] > ($this->config[$maxKey] ?? 0)) {
            $maxMb = round(($this->config[$maxKey] ?? 0) / 1024 / 1024);
            throw new \RuntimeException("File exceeds maximum size of {$maxMb} MB.");
        }
    }

    /**
     * Detect the embed provider from a URL.
     */
    private function detectProvider(string $url): ?string
    {
        if (preg_match('/youtube\.com|youtu\.be/i', $url)) {
            return 'youtube';
        }

        if (preg_match('/vimeo\.com/i', $url)) {
            return 'vimeo';
        }

        return null;
    }

    /**
     * Ensure a directory exists, creating it recursively if needed.
     */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
