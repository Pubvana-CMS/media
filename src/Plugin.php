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
                $instance = new MediaService(
                    Flight::db(),
                    $config,
                    $_SERVER['DOCUMENT_ROOT']
                );
            }
            return $instance;
        });

        $app->adext('menu', 'content', 'pubvana.media', [
            'label'    => 'Media',
            'icon'     => 'ti-photo',
            'url'      => '/media',
            'priority' => 20,
        ]);
    }
}
