<?php
namespace App\Controllers\Api\V1;
class AdminAuthController extends AuthController
{
    protected string $authRole = 'admin';
}
