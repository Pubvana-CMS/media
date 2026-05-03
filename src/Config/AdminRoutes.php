<?php

/**
 * Media admin routes.
 *
 * Auto-prefixed by Flight School. Prefix: /admin
 *
 * Routes:
 *   GET    /admin/media              - Media library page
 *   GET    /admin/media/json         - JSON listing for picker modal
 *   GET    /admin/media/capabilities - Processor capabilities
 *   POST   /admin/media/upload/image - Image upload
 *   POST   /admin/media/upload/video - Video upload
 *   POST   /admin/media/embed        - Store embed URL
 *   GET    /admin/media/@id/editor   - Image editor page
 *   POST   /admin/media/@id/edit     - Apply edit operation
 *   POST   /admin/media/@id/revert   - Revert to pristine original
 *   POST   /admin/media/@id/poster   - Upload poster for video
 *   POST   /admin/media/@id/update   - Update metadata
 *   POST   /admin/media/@id/delete   - Delete media + files
 *
 * @package Pubvana\Media\Config
 */

use Enlivenapp\FlightCsrf\Middlewares\CsrfMiddleware;
use Enlivenapp\FlightShield\Middlewares\SessionAuthMiddleware;
use Pubvana\Media\Controllers\MediaController;

/** @var \flight\net\Router $router */
/** @var \flight\Engine $app */
/** @var string $configPrepend */

// Library page
$router->get('/media', function () use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->index();
})->addMiddleware(new SessionAuthMiddleware($app));

// JSON listing for AJAX / picker modal
$router->get('/media/json', function () use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->json();
})->addMiddleware(new SessionAuthMiddleware($app));

// Image upload
$router->post('/media/upload/image', function () use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->uploadImage();
})->addMiddleware(new SessionAuthMiddleware($app))
  ->addMiddleware(new CsrfMiddleware($app));

// Video upload
$router->post('/media/upload/video', function () use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->uploadVideo();
})->addMiddleware(new SessionAuthMiddleware($app))
  ->addMiddleware(new CsrfMiddleware($app));

// Store embed
$router->post('/media/embed', function () use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->storeEmbed();
})->addMiddleware(new SessionAuthMiddleware($app))
  ->addMiddleware(new CsrfMiddleware($app));

// Upload poster for video
$router->post('/media/@id/poster', function (string $id) use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->uploadPoster($id);
})->addMiddleware(new SessionAuthMiddleware($app))
  ->addMiddleware(new CsrfMiddleware($app));

// Update metadata
$router->post('/media/@id/update', function (string $id) use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->update($id);
})->addMiddleware(new SessionAuthMiddleware($app))
  ->addMiddleware(new CsrfMiddleware($app));

// Delete
$router->post('/media/@id/delete', function (string $id) use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->destroy($id);
})->addMiddleware(new SessionAuthMiddleware($app))
  ->addMiddleware(new CsrfMiddleware($app));

// Image editor page
$router->get('/media/@id/editor', function (string $id) use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->editor($id);
})->addMiddleware(new SessionAuthMiddleware($app));

// Apply edit operation
$router->post('/media/@id/edit', function (string $id) use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->applyEdit($id);
})->addMiddleware(new SessionAuthMiddleware($app))
  ->addMiddleware(new CsrfMiddleware($app));

// Revert to original
$router->post('/media/@id/revert', function (string $id) use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->revert($id);
})->addMiddleware(new SessionAuthMiddleware($app))
  ->addMiddleware(new CsrfMiddleware($app));

// Processor capabilities
$router->get('/media/capabilities', function () use ($app, $configPrepend) {
    (new MediaController($app, $configPrepend))->capabilities();
})->addMiddleware(new SessionAuthMiddleware($app));
