<?php

declare(strict_types=1);

namespace Pubvana\Media\Services;

use Enlivenapp\FlightSchool\Exception\ConfigurationException;
use Enlivenapp\FlightSchool\Exception\ValidationException;
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

        throw new ConfigurationException('No image processing extension available. Install Imagick or GD.');
    }

    // ── Upload ─────────────────────────────────────────────────

    /**
     * Upload an image file.
     *
     * Stores a pristine original (never modified), creates a working copy,
     * and generates medium and thumbnail derivatives. Nothing else.
     *
     * @param array $file       $_FILES entry (name, tmp_name, size, type, error)
     * @param int   $uploadedBy User ID
     * @return Media The created media record
     */
    public function uploadImage(array $file, int $uploadedBy): Media
    {
        $this->validateUpload($file, 'image');

        $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $hex    = bin2hex(random_bytes(16));
        $relDir = $this->config['upload_path'] . '/' . date('Y/m');
        $absDir = $this->publicPath . '/' . $relDir;

        $this->ensureDirectory($absDir);
        $this->ensureDirectory($absDir . '/originals');
        $this->ensureDirectory($absDir . '/medium');
        $this->ensureDirectory($absDir . '/thumbs');

        $filename = $hex . '.' . $ext;

        // Pristine original — never touched again
        move_uploaded_file($file['tmp_name'], $absDir . '/originals/' . $filename);

        // Working copy
        copy($absDir . '/originals/' . $filename, $absDir . '/' . $filename);

        // Derivatives from working copy
        $this->generateDerivatives($absDir, $filename);

        return $this->model->createRecord([
            'type'        => 'image',
            'filename'    => $file['name'],
            'path'        => $relDir . '/' . $filename,
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
            ->resize($this->config['thumb_width'] ?? 300)
            ->toWebp($absDir . '/thumbs/' . $posterName, $this->config['webp_quality'] ?? 85);

        $media->updateMeta([
            'poster_path' => $relDir . '/thumbs/' . $posterName,
        ]);

        return $media;
    }

    // ── Editing ────────────────────────────────────────────────

    /**
     * Apply an editing operation to an image's working copy.
     *
     * Edits modify the working copy and regenerate derivatives.
     * The pristine original is never touched.
     *
     * @param int    $id        Media record ID
     * @param string $operation Operation name (crop, rotate, flip, etc.)
     * @param array  $params    Operation parameters
     * @return Media|null Updated record, or null if not found
     */
    public function applyEdit(int $id, string $operation, array $params = []): ?Media
    {
        $media = $this->model->findById($id);
        if ($media === null || $media->type !== 'image') {
            return null;
        }

        $workingPath = $this->publicPath . '/' . $media->path;
        if (!file_exists($workingPath)) {
            return null;
        }

        $this->processor->load($workingPath);

        match ($operation) {
            'crop'        => $this->processor->crop(
                (int) ($params['x'] ?? 0),
                (int) ($params['y'] ?? 0),
                (int) ($params['width'] ?? 0),
                (int) ($params['height'] ?? 0)
            ),
            'rotate'      => $this->processor->rotate((int) ($params['degrees'] ?? 90)),
            'flip'        => $this->processor->flip($params['direction'] ?? 'horizontal'),
            'sharpen'     => $this->processor->sharpen(),
            'brightness'  => $this->processor->brightness((int) ($params['level'] ?? 0)),
            'contrast'    => $this->processor->contrast((int) ($params['level'] ?? 0)),
            'auto_orient' => $this->processor->autoOrient(),
            'strip_exif'  => $this->processor->stripExif(),
            default       => throw new ValidationException("Unknown operation: {$operation}"),
        };

        $this->processor->save($workingPath);

        // Regenerate derivatives from edited working copy
        $absDir   = dirname($workingPath);
        $filename = basename($media->path);
        $this->generateDerivatives($absDir, $filename);

        $media->size       = filesize($workingPath);
        $media->updated_at = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $media->save();

        return $media;
    }

    /**
     * Revert an image to its pristine original.
     *
     * Copies the pristine original back to the working copy and
     * regenerates derivatives.
     */
    public function revert(int $id): ?Media
    {
        $media = $this->model->findById($id);
        if ($media === null || $media->type !== 'image') {
            return null;
        }

        $workingPath  = $this->publicPath . '/' . $media->path;
        $originalPath = dirname($workingPath) . '/originals/' . basename($media->path);

        if (!file_exists($originalPath)) {
            return null;
        }

        copy($originalPath, $workingPath);

        $absDir   = dirname($workingPath);
        $filename = basename($media->path);
        $this->generateDerivatives($absDir, $filename);

        $media->size       = filesize($workingPath);
        $media->updated_at = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $media->save();

        return $media;
    }

    /**
     * Get image dimensions and format info for a media record.
     *
     * @return array{width: int, height: int, mime: string}|null
     */
    public function getImageInfo(int $id): ?array
    {
        $media = $this->model->findById($id);
        if ($media === null || $media->type !== 'image') {
            return null;
        }

        $workingPath = $this->publicPath . '/' . $media->path;
        if (!file_exists($workingPath)) {
            return null;
        }

        return $this->processor->getInfo($workingPath);
    }

    /**
     * Get EXIF/metadata for a media record's working copy.
     *
     * @return array<string, string>
     */
    public function getExifData(int $id): array
    {
        $media = $this->model->findById($id);
        if ($media === null || $media->type !== 'image') {
            return [];
        }

        $workingPath = $this->publicPath . '/' . $media->path;
        if (!file_exists($workingPath)) {
            return [];
        }

        return $this->processor->getExif($workingPath);
    }

    /**
     * Get the list of operations the active processor supports.
     *
     * @return string[]
     */
    public function getCapabilities(): array
    {
        return $this->processor->capabilities();
    }

    // ── Derivatives ────────────────────────────────────────────

    /**
     * Generate medium and thumbnail WebP derivatives from the working copy.
     */
    private function generateDerivatives(string $absDir, string $filename): void
    {
        $hex         = pathinfo($filename, PATHINFO_FILENAME);
        $workingPath = $absDir . '/' . $filename;

        $this->ensureDirectory($absDir . '/medium');
        $this->ensureDirectory($absDir . '/thumbs');

        // Medium — proportional resize
        $this->processor
            ->load($workingPath)
            ->resize($this->config['medium_width'] ?? 768)
            ->toWebp($absDir . '/medium/' . $hex . '.webp', $this->config['webp_quality'] ?? 85);

        // Thumb — proportional resize
        $this->processor
            ->load($workingPath)
            ->resize($this->config['thumb_width'] ?? 300)
            ->toWebp($absDir . '/thumbs/' . $hex . '.webp', $this->config['webp_quality'] ?? 85);
    }

    // ── Metadata ───────────────────────────────────────────────

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

    // ── Deletion ───────────────────────────────────────────────

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

            // Working copy
            if (file_exists($basePath)) {
                unlink($basePath);
            }

            // Pristine original
            $original = $dir . '/originals/' . basename($basePath);
            if (file_exists($original)) {
                unlink($original);
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

    // ── Queries ────────────────────────────────────────────────

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
     * Count media items, optionally filtered by type.
     */
    public function countAll(?string $type = null): int
    {
        return $this->model->countAll($type);
    }

    /**
     * Get recent media items.
     *
     * @return Media[]
     */
    public function recent(int $limit = 5, ?string $type = null): array
    {
        return $this->model->paginate(1, $limit, $type);
    }

    /**
     * Find a single media record by ID.
     */
    public function find(int $id): ?Media
    {
        return $this->model->findById($id);
    }

    // ── Widgets ────────────────────────────────────────────────

    /**
     * Render a media image picker widget (admin — uses Tabler icons/JS).
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
     * Render a media image picker widget (public — uses Bootstrap Icons/JS).
     *
     * Same functionality as picker() but uses Bootstrap 5 icons and
     * bootstrap.Offcanvas instead of Tabler equivalents.
     *
     * @param string $inputName    Form field name (e.g. 'avatar')
     * @param string $currentValue Current image path (relative to public/)
     * @return string Rendered HTML
     */
    public function publicPicker(string $inputName, string $currentValue = ''): string
    {
        static $counter = 0;
        $pickerId = 'media-picker-' . (++$counter);

        ob_start();
        include __DIR__ . '/../Views/media/public_picker.php';
        return ob_get_clean();
    }

    /**
     * Render Jodit editor init with media integration.
     *
     * Returns HTML + JS that initialises Jodit on the given selector with:
     * - A custom "Media Library" toolbar button (browse + upload images/videos)
     * - Drag/drop image upload routed through the media package
     * - Jodit's built-in video button left intact for YouTube/Vimeo embeds
     *
     * @param string $selector CSS selector for the textarea (e.g. '#content')
     * @param array  $options  Override default Jodit config (height, buttons)
     * @return string Rendered HTML
     */
    public function joditInit(string $selector, array $options = []): string
    {
        static $counter = 0;
        $joditId = 'jodit-media-' . (++$counter);

        $defaults = [
            'height'  => 500,
            'buttons' => 'bold,italic,underline,strikethrough,|,ul,ol,|,outdent,indent,|,font,fontsize,brush,paragraph,|,image,video,table,link,|,align,undo,redo,|,hr,symbol,fullsize,source',
        ];
        $config = array_merge($defaults, $options);

        ob_start();
        include __DIR__ . '/../Views/media/jodit.php';
        return ob_get_clean();
    }

    // ── Internal ───────────────────────────────────────────────

    /**
     * Validate an uploaded file against size and extension rules.
     *
     * @param array  $file $_FILES entry
     * @param string $kind 'image' or 'video'
     * @throws ValidationException On validation failure
     */
    private function validateUpload(array $file, string $kind): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException('Upload failed with error code: ' . $file['error']);
        }

        $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedKey = ($kind === 'image') ? 'allowed_image_ext' : 'allowed_video_ext';
        $maxKey     = ($kind === 'image') ? 'max_image_size' : 'max_video_size';

        if (!in_array($ext, $this->config[$allowedKey] ?? [], true)) {
            throw new ValidationException("File type not allowed: .{$ext}");
        }

        if ($file['size'] > ($this->config[$maxKey] ?? 0)) {
            $maxMb = round(($this->config[$maxKey] ?? 0) / 1024 / 1024);
            throw new ValidationException("File exceeds maximum size of {$maxMb} MB.");
        }

        // Verify actual file content matches allowed MIME types
        $finfo     = new \finfo(FILEINFO_MIME_TYPE);
        $actualMime = $finfo->file($file['tmp_name']);

        $allowedMimes = ($kind === 'image')
            ? ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
            : ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v', 'application/mp4'];

        if (!in_array($actualMime, $allowedMimes, true)) {
            throw new ValidationException('File content does not match an allowed type.');
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
