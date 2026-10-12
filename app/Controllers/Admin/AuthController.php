<?php
namespace App\Controllers\Admin;
class AuthController extends \App\Controllers\Client\AuthController
{
    protected string $authRole = 'admin';
}
