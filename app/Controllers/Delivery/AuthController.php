<?php
namespace App\Controllers\Delivery;
class AuthController extends \App\Controllers\Client\AuthController
{
    protected string $authRole = 'delivery';
}
