<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Client\HomeController::index');
$routes->get('search', 'Client\ProductController::search');
$routes->get('orders', 'Client\OrderController::index');
$routes->get('products', 'Client\ProductController::index');
$routes->get('products/(:segment)', 'Client\ProductController::show/$1');
$routes->get('media/products/(:segment)', 'ProductMediaController::show/$1');
$routes->get('account', 'Client\AuthController::account');
$routes->get('checkout', 'Client\CheckoutController::index');

$routes->get('login', 'Client\AuthController::login');
$routes->post('login', 'Client\AuthController::attemptLogin');
$routes->get('signup', 'Client\AuthController::register');
$routes->post('signup', 'Client\AuthController::storeRegistration');
$routes->post('logout', 'Client\AuthController::logout', ['filter' => 'auth']);

$routes->group('admin', ['filter' => 'role:admin'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Admin\DashboardController::index');
    $routes->get('orders', 'Admin\OrderController::index');
    $routes->get('products', 'Admin\ProductController::index');
    $routes->get('customers', 'Admin\CustomerController::index');
    $routes->get('delivery-personnel', 'Admin\DeliveryPersonnelController::index');
    $routes->get('dispatch', 'Admin\DispatchController::index');
    $routes->get('cash', 'Admin\CashController::index');
    $routes->get('settings', 'Admin\SettingsController::index');
    $routes->get('offers', 'Admin\CampaignController::offers');
    $routes->get('promotions', 'Admin\CampaignController::promotions');
});

$routes->group('delivery', ['filter' => 'role:delivery'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Delivery\DashboardController::index');
});

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes): void {
    $routes->get('csrf', 'CsrfController::show');

    $routes->get('products', 'ProductController::index');
    $routes->get('products/(:segment)', 'ProductController::show/$1');

    $routes->get('cart', 'CartController::show');
    $routes->post('cart/items', 'CartController::addItem');
    $routes->patch('cart/items/(:segment)', 'CartController::updateItem/$1');
    $routes->delete('cart/items/(:segment)', 'CartController::removeItem/$1');
    $routes->delete('cart', 'CartController::clear');

    $routes->get('checkout/summary', 'CheckoutController::summary', ['filter' => 'apiRole:customer']);
    $routes->post('checkout/offer', 'CheckoutController::applyOffer', ['filter' => 'apiRole:customer']);
    $routes->delete('checkout/offer', 'CheckoutController::removeOffer', ['filter' => 'apiRole:customer']);
    $routes->post('checkout/otp/start', 'CheckoutController::startOtp', ['filter' => 'apiRole:customer']);
    $routes->post('checkout/otp/verify', 'CheckoutController::verifyOtp', ['filter' => 'apiRole:customer']);
    $routes->post('checkout/place', 'CheckoutController::place', ['filter' => 'apiRole:customer']);

    $routes->post('auth/register', 'AuthController::register');
    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/otp/start', 'AuthController::startOtp');
    $routes->post('auth/otp/verify', 'AuthController::verifyOtp');
    $routes->post('auth/logout', 'AuthController::logout', ['filter' => 'apiAuth']);
    $routes->get('auth/me', 'AuthController::me', ['filter' => 'apiAuth']);

    $routes->get('account/profile', 'AccountController::profile', ['filter' => 'apiRole:customer']);
    $routes->patch('account/profile', 'AccountController::updateProfile', ['filter' => 'apiRole:customer']);
    $routes->post('orders/(:segment)/delivery-pin', 'DeliveryPinController::create/$1', ['filter' => 'apiRole:customer']);

    $routes->group('admin', ['namespace' => 'App\Controllers\Api\V1\Admin', 'filter' => 'apiRole:admin'], static function (RouteCollection $routes): void {
        $routes->get('dashboard', 'DashboardController::show');
        $routes->get('settings', 'SettingsController::show');
        $routes->put('settings', 'SettingsController::update');
        $routes->get('offers', 'OfferController::index');
        $routes->post('offers', 'OfferController::create');
        $routes->get('offers/(:segment)', 'OfferController::show/$1');
        $routes->put('offers/(:segment)', 'OfferController::update/$1');
        $routes->get('promotions', 'PromotionController::index');
        $routes->post('promotions', 'PromotionController::create');
        $routes->get('promotions/(:segment)', 'PromotionController::show/$1');
        $routes->put('promotions/(:segment)', 'PromotionController::update/$1');
        $routes->get('cash', 'CashController::index');
        $routes->post('cash/(:segment)/reconcile', 'CashController::reconcile/$1');
        $routes->get('dispatch', 'DispatchController::index');
        $routes->get('dispatch/candidates', 'DispatchController::candidates');
        $routes->post('orders/(:segment)/assignment', 'DispatchController::assign/$1');
        $routes->get('delivery-personnel', 'DeliveryPersonnelController::index');
        $routes->post('delivery-personnel', 'DeliveryPersonnelController::create');
        $routes->get('delivery-personnel/(:segment)', 'DeliveryPersonnelController::show/$1');
        $routes->patch('delivery-personnel/(:segment)/status', 'DeliveryPersonnelController::updateStatus/$1');
        $routes->patch('delivery-personnel/(:segment)/availability', 'DeliveryPersonnelController::updateAvailability/$1');
        $routes->get('customers', 'CustomerController::index');
        $routes->get('customers/(:segment)', 'CustomerController::show/$1');
        $routes->patch('customers/(:segment)/status', 'CustomerController::updateStatus/$1');
        $routes->get('products', 'ProductController::index');
        $routes->post('products', 'ProductController::create');
        $routes->get('products/(:segment)', 'ProductController::show/$1');
        $routes->put('products/(:segment)', 'ProductController::update/$1');
        $routes->patch('products/(:segment)', 'ProductController::update/$1');
        $routes->delete('products/(:segment)', 'ProductController::delete/$1');
        $routes->post('products/(:segment)/image', 'ProductController::uploadImage/$1');
        $routes->delete('products/(:segment)/image', 'ProductController::removeImage/$1');

        $routes->get('orders', 'OrderController::index');
        $routes->get('orders/(:segment)', 'OrderController::show/$1');
        $routes->patch('orders/(:segment)/status', 'OrderController::updateStatus/$1');
    });

    $routes->group('delivery', ['namespace' => 'App\Controllers\Api\V1\Delivery', 'filter' => 'apiRole:delivery'], static function (RouteCollection $routes): void {
        $routes->post('orders/(:segment)/complete', 'OrderController::complete/$1');
        $routes->get('orders', 'OrderController::index');
        $routes->get('orders/(:segment)', 'OrderController::show/$1');
        $routes->patch('orders/(:segment)/status', 'OrderController::updateStatus/$1');
    });
});
