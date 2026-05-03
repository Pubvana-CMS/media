<?php

/**
 * @package   Pubvana\Media
 * @copyright 2026 Pubvana
 * @license   MIT
 */

return [
    'routePrepend'      => 'media',
    'upload_path'       => 'uploads',
    'max_image_size'    => 10 * 1024 * 1024, // 10 MB
    'max_video_size'    => 100 * 1024 * 1024, // 100 MB
    'allowed_image_ext' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'allowed_video_ext' => ['mp4', 'webm', 'mov'],
    'webp_quality'      => 85,
    'thumb_width'       => 300,
    'medium_width'      => 768,
];
