[![Version](http://poser.pugx.org/pubvana/media/version)](https://packagist.org/packages/pubvana/media)
[![License](http://poser.pugx.org/pubvana/media/license)](https://packagist.org/packages/pubvana/media)
[![PHP Version Require](http://poser.pugx.org/pubvana/media/require/php)](https://packagist.org/packages/pubvana/media)

# Pubvana Media

Media management module for [Pubvana](https://pubvana.com) — images, video, and embeds. Built as a [Flight School](https://github.com/enlivenapp/flight-school) plugin with the headless service pattern.

## Features

- Image uploads with automatic WebP derivatives (medium + thumbnail)
- Video uploads with optional ffmpeg poster extraction
- Embed storage (YouTube, Vimeo)
- Imagick or GD — auto-detects the best available processor
- Reusable media picker widget for any admin form
- Headless service on `$app->media()` — usable from any controller or plugin

## Requirements

- PHP 8.1+
- `enlivenapp/flight-school` ^0.2
- `enlivenapp/flight-shield` ^0.1
- `pubvana/admin`
- Imagick or GD extension
- ffmpeg *(optional, for video poster extraction)*

## Installation

```bash
composer require pubvana/media
```

Enable in `app/config/config.php`:

```php
'plugins' => [
    'pubvana/media' => [
        'enabled'  => true,
        'priority' => 60,
    ],
],
```

The migration creates the `media` table automatically on first load.

## Configuration

Defaults from `Config.php` — override in your plugin config block:

| Key | Default | Description |
|-----|---------|-------------|
| `upload_path` | `'uploads'` | Relative to public/ |
| `max_image_size` | `10485760` | 10 MB |
| `max_video_size` | `104857600` | 100 MB |
| `allowed_image_ext` | `['jpg','jpeg','png','gif','webp']` | |
| `allowed_video_ext` | `['mp4','webm','mov']` | |
| `webp_quality` | `85` | WebP output quality (0-100) |
| `thumb_width` | `300` | Thumbnail crop width |
| `thumb_height` | `200` | Thumbnail crop height |
| `medium_width` | `768` | Medium resize width (proportional) |

## Usage

### Headless service

```php
$media = Flight::media();

// Upload an image
$record = $media->uploadImage($_FILES['photo'], user_id());

// Upload a video
$record = $media->uploadVideo($_FILES['clip'], user_id());

// Store an embed
$record = $media->storeEmbed('https://youtube.com/watch?v=...', user_id());

// List with pagination
$result = $media->list(page: 1, perPage: 24, type: 'image');
// $result = ['items' => [...], 'total' => 42, 'page' => 1, 'per_page' => 24]

// Find / update / delete
$record = $media->find(5);
$media->updateMeta(5, ['alt_text' => 'Sunset photo']);
$media->delete(5); // removes DB record + all files
```

### Picker widget

Embed the media picker in any admin form:

```php
<?= Flight::media()->picker('avatar', $profile->avatar ?? '') ?>
```

Renders a clickable thumbnail with an offcanvas browser. The selected image path is written to a hidden input with the given name.

## File structure

Uploads go to `public/{upload_path}/YYYY/MM/`:

```
uploads/2026/04/
    abc123.jpg          # original
    medium/abc123.webp  # 768px wide, proportional
    thumbs/abc123.webp  # 300x200, cropped
```

## Admin

The module registers a **Media** item in the admin content menu. The admin page provides a grid view for browsing, uploading, and managing media files.

## License

MIT
