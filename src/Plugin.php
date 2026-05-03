<?php

declare(strict_types=1);

namespace Pubvana\Media;

use Enlivenapp\FlightSchool\PluginInterface;
use Pubvana\Media\Services\MediaService;
use flight\Engine;
use flight\net\Router;
use Flight;

/**
 * Flight School plugin registration for the Media module.
 *
 * Registers the headless media service on the app and the admin menu entry.
 *
 * @package Pubvana\Media
 */
class Plugin implements PluginInterface
{
    /**
     * Register the media service and admin menu entry.
     *
     * @param Engine $app    The FlightPHP application instance
     * @param Router $router The FlightPHP router
     * @param array  $config Plugin config values from Config.php
     */
    public function register(Engine $app, Router $router, array $config = []): void
    {
        // Map the media service (singleton)
        $app->map('media', function () use ($config) {
            static $instance = null;
            if ($instance === null) {
                $publicPath = defined('FC_PATH')
                    ? FC_PATH
                    : rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

                $instance = new MediaService(
                    Flight::db(),
                    $config,
                    $publicPath
                );
            }
            return $instance;
        });

        // Register Vision tag: {% media_picker 'fieldname' currentValue %}
        $view = $app->view();
        if ($view instanceof \Enlivenapp\FlightSchool\PluginView) {
            $engine = $view->vision();
            if ($engine !== null) {
                $engine->tags()->register('media_picker', function (string $inputName, ?string $currentValue = '') use ($app) {
                    $currentValue = $currentValue ?? '';
                    return $app->media()->publicPicker($inputName, $currentValue);
                });
            }
        }

        $app->adext('menu', 'content', 'pubvana.media', [
            'label'    => 'Media',
            'icon'     => 'ti-photo',
            'url'      => '/media',
            'priority' => 20,
        ]);

        $app->adext('page', 'dashboard.cards', 'pubvana.media', [
            'label'    => 'Media',
            'priority' => 40,
            'callable' => function (array $context) use ($app): array {
                $total = $app->media()->countAll();

                return [[
                    'id'          => 'media-items',
                    'label'       => 'Media Items',
                    'value'       => $total,
                    'icon'        => 'ti-photo',
                    'tone'        => 'secondary',
                    'href'        => '/media',
                    'description' => 'Assets available in the media library.',
                ]];
            },
        ]);

        $app->adext('page', 'dashboard.sections', 'pubvana.media', [
            'label'    => 'Media',
            'priority' => 50,
            'callable' => function (array $context) use ($app): array {
                $items = [];
                foreach ($app->media()->recent(5) as $media) {
                    $type = ucfirst((string) $media->type);
                    $items[] = [
                        'label'    => $media->title ?: $media->filename,
                        'meta'     => $type . ' · ' . date('M j, Y g:ia', strtotime((string) $media->created_at)),
                        'href'     => '/media',
                        'emphasis' => 'secondary',
                    ];
                }

                return [[
                    'id'          => 'recent-media',
                    'title'       => 'Recent Uploads',
                    'type'        => 'list',
                    'icon'        => 'ti-photo-up',
                    'href'        => '/media',
                    'empty_state' => 'The media library is still empty.',
                    'items'       => $items,
                ]];
            },
        ]);
    }
}
