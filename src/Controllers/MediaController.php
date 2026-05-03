<?php

declare(strict_types=1);

namespace Pubvana\Media\Controllers;

use Enlivenapp\FlightSchool\Exception\ValidationException;
use Pubvana\Admin\Controllers\AdminController;
use Pubvana\Media\Services\MediaService;

/**
 * Admin controller for media library — uploads, listing, metadata, deletion.
 *
 * All routes are admin-prefixed via AdminRoutes.php. JSON responses are used
 * for upload, update, and delete operations so the media picker modal and
 * library page can work via AJAX.
 *
 * @package Pubvana\Media\Controllers
 */
class MediaController extends AdminController
{
    /**
     * Get the MediaService from the app container.
     *
     * @return MediaService
     */
    protected function service(): MediaService
    {
        return $this->app->media();
    }

    /**
     * Media library page — paginated grid with upload zone.
     *
     * Reads ?page and ?type from the query string for pagination and filtering.
     */
    public function index(): void
    {
        $request = $this->app->request();
        $page    = max(1, (int) ($request->query->page ?? 1));
        $type    = $request->query->type ?? null;

        if ($type !== null && !in_array($type, ['image', 'video', 'embed'], true)) {
            $type = null;
        }

        $result = $this->service()->list($page, 24, $type);

        $this->render('media/index', [
            'pageTitle'  => 'Media Library',
            'media'      => $result['items'],
            'total'      => $result['total'],
            'page'       => $result['page'],
            'perPage'    => $result['per_page'],
            'typeFilter' => $type,
        ]);
    }

    /**
     * JSON endpoint for the media picker modal pagination.
     *
     * Returns a JSON array of media records for AJAX grid loading.
     */
    public function json(): void
    {
        $request = $this->app->request();
        $page    = max(1, (int) ($request->query->page ?? 1));
        $type    = $request->query->type ?? null;

        if ($type !== null && !in_array($type, ['image', 'video', 'embed'], true)) {
            $type = null;
        }

        $result = $this->service()->list($page, 24, $type);

        $items = array_map(function ($m) {
            return $this->mediaToArray($m);
        }, $result['items']);

        $this->app->json([
            'items'    => $items,
            'total'    => $result['total'],
            'page'     => $result['page'],
            'per_page' => $result['per_page'],
        ]);
    }

    /**
     * Handle image upload. Expects a multipart form POST with a `file` field.
     *
     * Returns the created media record as JSON on success, or a JSON error
     * with an appropriate HTTP status on failure.
     */
    public function uploadImage(): void
    {
        $file = $this->app->request()->files->file ?? null;

        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            $this->app->json(['error' => 'No file uploaded or upload error.'], 400);
            return;
        }

        try {
            $user  = $this->app->auth()->user();
            $media = $this->service()->uploadImage($file, (int) $user->id);
            $this->app->json($this->mediaToArray($media), 201);
        } catch (ValidationException $e) {
            $this->app->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Handle video upload. Expects a multipart form POST with a `file` field.
     *
     * Returns the created media record as JSON. If ffmpeg is unavailable,
     * poster_path will be null — the client should offer poster upload.
     */
    public function uploadVideo(): void
    {
        $file = $this->app->request()->files->file ?? null;

        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            $this->app->json(['error' => 'No file uploaded or upload error.'], 400);
            return;
        }

        try {
            $user  = $this->app->auth()->user();
            $media = $this->service()->uploadVideo($file, (int) $user->id);
            $this->app->json($this->mediaToArray($media), 201);
        } catch (ValidationException $e) {
            $this->app->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Store an embed URL (YouTube, Vimeo, etc.).
     *
     * Expects a POST with `url` in the request body.
     */
    public function storeEmbed(): void
    {
        $url = trim($this->app->request()->data->url ?? '');

        if ($url === '') {
            $this->app->json(['error' => 'URL is required.'], 400);
            return;
        }

        $user  = $this->app->auth()->user();
        $media = $this->service()->storeEmbed($url, (int) $user->id);

        $this->app->json($this->mediaToArray($media), 201);
    }

    /**
     * Upload a poster image for an existing video media record.
     *
     * @param string $id Media record ID from the URL segment
     */
    public function uploadPoster(string $id): void
    {
        $media = $this->service()->find((int) $id);

        if ($media === null || $media->type !== 'video') {
            $this->app->json(['error' => 'Video not found.'], 404);
            return;
        }

        $file = $this->app->request()->files->file ?? null;

        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            $this->app->json(['error' => 'No file uploaded.'], 400);
            return;
        }

        try {
            $media = $this->service()->uploadPoster($media, $file);
            $this->app->json($this->mediaToArray($media));
        } catch (ValidationException $e) {
            $this->app->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update metadata (alt_text, title) on a media record.
     *
     * @param string $id Media record ID from the URL segment
     */
    public function update(string $id): void
    {
        $data = $this->app->request()->data->getData();
        unset($data['_csrf_token']);

        $media = $this->service()->updateMeta((int) $id, $data);

        if ($media === null) {
            $this->app->json(['error' => 'Media not found.'], 404);
            return;
        }

        $this->app->json($this->mediaToArray($media));
    }

    /**
     * Delete a media record and its associated files.
     *
     * @param string $id Media record ID from the URL segment
     */
    public function destroy(string $id): void
    {
        if (!$this->service()->delete((int) $id)) {
            $this->app->json(['error' => 'Media not found.'], 404);
            return;
        }

        $this->app->json(['success' => true]);
    }

    /**
     * Image editor page.
     *
     * @param string $id Media record ID from the URL segment
     */
    public function editor(string $id): void
    {
        $media = $this->service()->find((int) $id);

        if ($media === null || $media->type !== 'image') {
            $this->app->redirect('/admin/media');
            return;
        }

        $info         = $this->service()->getImageInfo((int) $id);
        $capabilities = $this->service()->getCapabilities();
        $exifData     = $this->service()->getExifData((int) $id);

        $this->render('media/editor', [
            'pageTitle'    => 'Edit — ' . ($media->title ?: $media->filename),
            'media'        => $media,
            'info'         => $info,
            'capabilities' => $capabilities,
            'exifData'     => $exifData,
        ]);
    }

    /**
     * Apply an edit operation to an image's working copy.
     *
     * Expects POST with `operation` and `params` (JSON string) fields.
     *
     * @param string $id Media record ID from the URL segment
     */
    public function applyEdit(string $id): void
    {
        $data      = $this->app->request()->data;
        $operation = trim($data->operation ?? '');
        $params    = json_decode($data->params ?? '{}', true) ?: [];

        $caps = $this->service()->getCapabilities();
        if (!in_array($operation, $caps, true)) {
            $this->app->json(['error' => 'Unsupported operation.'], 400);
            return;
        }

        try {
            $media = $this->service()->applyEdit((int) $id, $operation, $params);
        } catch (ValidationException $e) {
            $this->app->json(['error' => $e->getMessage()], 422);
            return;
        }

        if ($media === null) {
            $this->app->json(['error' => 'Image not found.'], 404);
            return;
        }

        $info   = $this->service()->getImageInfo((int) $id);
        $result = $this->mediaToArray($media);
        $result['info'] = $info;
        $result['exif'] = $this->service()->getExifData((int) $id);

        $this->app->json($result);
    }

    /**
     * Revert an image to its pristine original.
     *
     * @param string $id Media record ID from the URL segment
     */
    public function revert(string $id): void
    {
        $media = $this->service()->revert((int) $id);

        if ($media === null) {
            $this->app->json(['error' => 'Image not found or no original available.'], 404);
            return;
        }

        $info   = $this->service()->getImageInfo((int) $id);
        $result = $this->mediaToArray($media);
        $result['info'] = $info;
        $result['exif'] = $this->service()->getExifData((int) $id);

        $this->app->json($result);
    }

    /**
     * Return processor capabilities as JSON.
     */
    public function capabilities(): void
    {
        $this->app->json(['capabilities' => $this->service()->getCapabilities()]);
    }

    /**
     * Normalize a Media entity into a plain array for JSON responses.
     *
     * Includes computed URL fields (thumb_url, medium_url) derived from
     * the stored path by convention, so the client never builds paths.
     *
     * @param \Pubvana\Media\Models\Media $media
     * @return array<string, mixed>
     */
    private function mediaToArray($media): array
    {
        $data = [
            'id'             => (int) $media->id,
            'type'           => $media->type,
            'filename'       => $media->filename,
            'path'           => $media->path,
            'mime_type'      => $media->mime_type,
            'size'           => $media->size ? (int) $media->size : null,
            'alt_text'       => $media->alt_text,
            'title'          => $media->title,
            'embed_url'      => $media->embed_url,
            'embed_provider' => $media->embed_provider,
            'poster_path'    => $media->poster_path,
            'uploaded_by'    => (int) $media->uploaded_by,
            'created_at'     => $media->created_at,
            'updated_at'     => $media->updated_at,
        ];

        // Computed URLs for image derivatives
        if ($media->type === 'image' && $media->path) {
            $dir  = dirname($media->path);
            $name = pathinfo($media->path, PATHINFO_FILENAME);

            $data['url']        = '/' . $media->path;
            $data['thumb_path'] = $dir . '/thumbs/' . $name . '.webp';
            $data['medium_path'] = $dir . '/medium/' . $name . '.webp';
            $data['thumb_url']  = '/' . $dir . '/thumbs/' . $name . '.webp';
            $data['medium_url'] = '/' . $dir . '/medium/' . $name . '.webp';
        } elseif ($media->path) {
            $data['url'] = '/' . $media->path;
        }

        if ($media->poster_path) {
            $data['poster_url'] = '/' . $media->poster_path;
        }

        return $data;
    }
}
