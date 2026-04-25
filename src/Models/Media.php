<?php

declare(strict_types=1);

namespace Pubvana\Media\Models;

/**
 * Media ActiveRecord model.
 *
 * @property int         $id
 * @property string      $type            image|video|embed
 * @property string      $filename        Original upload filename (or URL for embeds)
 * @property string|null $path            Relative path from public/ (null for embeds)
 * @property string|null $mime_type
 * @property int|null    $size            File size in bytes
 * @property string|null $alt_text
 * @property string|null $title
 * @property string|null $embed_url       Full URL for embed types
 * @property string|null $embed_provider  youtube|vimeo|null
 * @property string|null $poster_path     Video poster/thumbnail relative path
 * @property int         $uploaded_by     User ID
 * @property string      $created_at
 * @property string      $updated_at
 */
class Media extends \flight\ActiveRecord
{
    public function __construct($pdo = null, array $config = [])
    {
        parent::__construct($pdo, 'media', $config);
    }

    /**
     * Find a single media record by ID.
     */
    public function findById(int $id): ?self
    {
        $this->reset();
        $this->eq('id', $id)->find();

        return $this->isHydrated() ? $this : null;
    }

    /**
     * Paginated listing, optionally filtered by type.
     *
     * @param int         $page     1-based page number
     * @param int         $perPage  Items per page
     * @param string|null $type     Filter: 'image', 'video', 'embed', or null for all
     * @return self[]
     */
    public function paginate(int $page = 1, int $perPage = 24, ?string $type = null): array
    {
        $query = new self($this->getDatabaseConnection());

        if ($type !== null) {
            $query->eq('type', $type);
        }

        return $query->order('id DESC')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->findAll();
    }

    /**
     * Count total media records, optionally filtered by type.
     */
    public function countAll(?string $type = null): int
    {
        $query = new self($this->getDatabaseConnection());
        $query->select('COUNT(*) as cnt');

        if ($type !== null) {
            $query->eq('type', $type);
        }

        $result = $query->find();

        return (int) $result->cnt;
    }

    /**
     * Create a new media record.
     *
     * @param array<string, mixed> $data Column => value pairs
     * @return self The inserted record
     */
    public function createRecord(array $data): self
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $media = new self($this->getDatabaseConnection());

        foreach ($data as $key => $value) {
            $media->$key = $value;
        }

        $media->created_at = $now;
        $media->updated_at = $now;
        $media->insert();

        return $media;
    }

    /**
     * Update metadata fields on this record.
     *
     * @param array<string, mixed> $data Allowed fields to set
     */
    public function updateMeta(array $data): void
    {
        $allowed = ['alt_text', 'title', 'poster_path'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $this->$field = trim((string) $data[$field]) ?: null;
            }
        }

        $this->updated_at = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->save();
    }
}
