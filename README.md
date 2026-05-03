[![Stable? Not Quite Yet](https://img.shields.io/badge/stable%3F-not%20quite%20yet-blue?style=for-the-badge)](https://packagist.org/packages/pubvana/media)
[![License](https://img.shields.io/packagist/l/pubvana/media?style=for-the-badge)](https://packagist.org/packages/pubvana/media)
[![PHP Version](https://img.shields.io/packagist/php-v/pubvana/media?style=for-the-badge)](https://packagist.org/packages/pubvana/media)
[![Monthly Downloads](https://img.shields.io/packagist/dm/pubvana/media?style=for-the-badge)](https://packagist.org/packages/pubvana/media)
[![Total Downloads](https://img.shields.io/packagist/dt/pubvana/media?style=for-the-badge)](https://packagist.org/packages/pubvana/media)
[![GitHub Issues](https://img.shields.io/github/issues/Pubvana-CMS/media?style=for-the-badge)](https://github.com/Pubvana-CMS/media/issues)
[![Contributors](https://img.shields.io/github/contributors/Pubvana-CMS/media?style=for-the-badge)](https://github.com/Pubvana-CMS/media/graphs/contributors)
[![Latest Release](https://img.shields.io/github/v/release/Pubvana-CMS/media?style=for-the-badge)](https://github.com/Pubvana-CMS/media/releases)
[![Contributions Welcome](https://img.shields.io/badge/contributions-welcome-blue?style=for-the-badge)](https://github.com/Pubvana-CMS/media/pulls)

# Pubvana Media

**I noticed folks downloading some of these packages. I'm super grateful, Thank You!  I would like to let folks know until this notice disappears I'm doing a lot of breaking changes without worrying about them.  Once versions are up around 0.5.x things should settle down.**

Media management module for [Pubvana](https://pubvanacms.com) — images, video, and embeds. Built as a [Flight School](https://github.com/enlivenapp/flight-school) plugin.

## Features

- Image uploads with automatic WebP derivatives (medium + thumbnail)
- Video uploads with optional ffmpeg poster extraction
- Embed storage (YouTube, Vimeo)
- Imagick or GD — auto-detects the best available processor
- Reusable media picker widget for any form
- Media service on `$app->media()` — usable from any controller or plugin
- Registers an `adext` menu contribution when admin is present

## Requirements

- PHP 8.1+
- `enlivenapp/flight-school`
- `enlivenapp/flight-shield`
- `enlivenapp/migrations`
- `flightphp/active-record`
- Imagick or GD extension
- ffmpeg *(optional, for video poster extraction)*

## Recommends

- `pubvana/admin` (Admin UI for the media library and picker workflows)

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

Migrations package creates the `media` table automatically on first load.

## Flight School config

This package uses Flight School's return-array config format. `src/Config/Config.php` returns the package defaults as an array, and Flight School stores that array under `pubvana.media` on `$app`.

That returned array currently includes `'routePrepend' => 'media'`. The package does not currently define public application routes in `src/Config/Routes.php`, but admin/media-related routes still use the normal Flight School loading flow.

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
| `thumb_width` | `300` | Small derivative width (proportional resize) |
| `thumb_height` | `200` | Reserved for poster sizing / legacy config compatibility |
| `medium_width` | `768` | Medium resize width (proportional) |

## Usage

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

Embed the media picker in any form:

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
    thumbs/abc123.webp  # 300px wide, proportional
```

## License

MIT
