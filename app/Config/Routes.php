<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Client\HomeController::index');
$routes->get('products', 'Client\ProductController::index');
$routes->get('products/(:segment)', 'Client\ProductController::show/$1');

$routes->get('login', 'Client\AuthController::login');
$routes->post('login', 'Client\AuthController::attemptLogin');
$routes->get('signup', 'Client\AuthController::register');
$routes->post('signup', 'Client\AuthController::storeRegistration');
$routes->get('logout', 'Client\AuthController::logout', ['filter' => 'auth']);

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('account', 'Client\AuthController::account');
});

$routes->group('admin', ['filter' => 'role:admin'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Admin\DashboardController::index');
});

$routes->group('delivery', ['filter' => 'role:delivery'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Delivery\DashboardController::index');
});

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes): void {
    $routes->get('csrf', 'CsrfController::show');

    $routes->get('products', 'ProductController::index');
    $routes->get('products/(:segment)', 'ProductController::show/$1');

    $routes->post('auth/register', 'AuthController::register');
    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/logout', 'AuthController::logout', ['filter' => 'apiAuth']);
    $routes->get('auth/me', 'AuthController::me', ['filter' => 'apiAuth']);

    $routes->group('admin', ['namespace' => 'App\Controllers\Api\V1\Admin', 'filter' => 'apiRole:admin'], static function (RouteCollection $routes): void {
        $routes->get('products', 'ProductController::index');
        $routes->post('products', 'ProductController::create');
        $routes->get('products/(:segment)', 'ProductController::show/$1');
        $routes->put('products/(:segment)', 'ProductController::update/$1');
        $routes->patch('products/(:segment)', 'ProductController::update/$1');
        $routes->delete('products/(:segment)', 'ProductController::delete/$1');

        $routes->get('orders', 'OrderController::index');
        $routes->get('orders/(:segment)', 'OrderController::show/$1');
        $routes->patch('orders/(:segment)/status', 'OrderController::updateStatus/$1');
    });

    $routes->group('delivery', ['namespace' => 'App\Controllers\Api\V1\Delivery', 'filter' => 'apiRole:delivery'], static function (RouteCollection $routes): void {
        $routes->get('orders', 'OrderController::index');
        $routes->get('orders/(:segment)', 'OrderController::show/$1');
        $routes->patch('orders/(:segment)/status', 'OrderController::updateStatus/$1');
    });
});
