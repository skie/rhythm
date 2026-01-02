<?php
declare(strict_types=1);

/**
 * Rhythm Plugin Routes
 *
 * Routes for Rhythm performance monitoring plugin.
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return static function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);

    $routes->plugin('Rhythm', ['path' => '/rhythm'], function (RouteBuilder $routes): void {
        $routes->connect('/dashboard', ['controller' => 'Dashboard', 'action' => 'index']);
        $routes->connect('/dashboard/refresh', ['controller' => 'Dashboard', 'action' => 'refresh']);
        $routes->connect('/dashboard/widget/*', ['controller' => 'Dashboard', 'action' => 'widget']);

        $routes->fallbacks();
    });
};
